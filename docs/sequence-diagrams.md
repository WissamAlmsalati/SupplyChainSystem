# الساحل لمستلزمات المقاهي — Sequence Diagrams

These Mermaid diagrams describe the main business flows exposed by the API documented at `/api/documentation`.

## 1. Order checkout flow

The cafe's app posts one request, and everything below happens inside it. The
whole thing is one transaction: if any step throws, no order, no stock movement
and no payment survives.

`OrderPlacementService::place()` is the only path that creates an order. The
dashboard (`POST /orders`) and a recurring cart enter the same method with a
different `OrderSource`, which is why the coverage guard below checks the source:
the office may still send an order to an uncovered address on purpose.

```mermaid
sequenceDiagram
    autonumber
    actor Cafe as Cafe (customer app)
    participant API as CustomerMobileController::checkout
    participant Place as OrderPlacementService
    participant Order as Order (model hooks)
    participant Stock as StockService
    participant Wallet as WalletService
    participant Assign as DelegateAssignmentService
    participant Notify as OrderNotifier / Notification
    participant DB as MySQL

    Cafe->>API: POST /api/v1/customer/checkout
    Note over Cafe,API: {address_id, payment_method, note}<br/>Idempotency-Key optional: a repeat<br/>replays the first answer for a day.

    API->>DB: load this cafe's shopping cart
    alt cart is empty
        API-->>Cafe: 400 السلة فارغة
    end
    API->>DB: find the address, scoped to this cafe
    alt the address belongs to someone else
        API-->>Cafe: 404
    end

    rect rgb(244, 246, 245)
    Note over API,DB: DB::transaction — all of it, or none of it

    API->>Place: place(customer, address, cart items, OrderSource::App, ...)

    Place->>DB: load active variants of active products
    alt a size is hidden, deleted or unknown
        Place-->>Cafe: 422 بعض المنتجات غير متاحة
    end

    Place->>DB: load address.deliveryZone
    alt from the app, and no active zone reaches the address
        Place-->>Cafe: 422 هذا العنوان خارج نطاق التوصيل حالياً
    end

    Note over Place: Prices and costs are read from the server,<br/>never from the request. unit_cost is<br/>snapshotted beside unit_price, so profit<br/>is measured against what it cost then.

    Place->>Order: new Order(subtotal, delivery_fee from the zone, total)
    Order->>DB: insert order — number ORD-Y-m-d-H-NNN in Tripoli time,<br/>status pending, delivery address snapshotted
    Order->>DB: insert order_status_logs row
    Place->>DB: insert order_items

    Place->>Stock: drainForOrder(order, quantities, the zone's warehouse)
    Stock->>DB: SELECT … FOR UPDATE on inventories
    alt not enough stock across every warehouse
        Stock-->>Cafe: 422 InsufficientStockException
    end
    Stock->>DB: decrement quantities, the zone's warehouse first
    Stock->>DB: insert stock_movements (Sale, referenced to the order)
    Stock-->>Place: which warehouse to load from
    Place->>DB: order.warehouse_id

    opt payment_method = wallet
        Place->>Wallet: payOrder(order, customer)
        Wallet->>DB: debit the wallet in cents + wallet_transactions row
        alt the balance is short
            Wallet-->>Cafe: 422 — the whole order rolls back
        end
        Wallet->>DB: insert payments row (wallet, paid)
    end

    Place->>Assign: assignNearest(order)
    Note over Assign: Nearest delegate that is active, has the<br/>flag set, and reported a location within<br/>the last 30 minutes. None is not an error.
    Assign->>DB: order.delegate_id
    Order->>Notify: delegateAssigned() — the driver is told
    Place->>Notify: notifyAdmins("طلب جديد")

    Place-->>API: order
    API->>DB: empty the cart
    end

    API-->>Cafe: 201 Created + the order
```

What the flow refuses, and why it is refused there rather than in the client:

| Guard | Answer | Lives in |
| --- | --- | --- |
| Payment method other than cash or wallet | 422 | `place()` |
| Empty basket, or a size whose product was hidden | 422 | `place()` |
| Address outside every active zone, from an app | 422 | `place()` |
| Stock short anywhere in the company | 422 | `StockService` |
| Wallet balance short | 422, everything rolls back | `WalletService` |

## 2. Purchase order flow

```mermaid
sequenceDiagram
    actor A as Admin
    participant INV as InventoryController
    participant SS as StockService
    participant DB as Database

    A->>INV: POST /inventory (warehouse, variant, quantity, unit_cost, expiry_date)
    INV->>SS: receive()
    SS->>DB: lock/insert inventories row, quantity += received
    SS->>DB: insert stock_movements (type=purchase, cost, expiry)
    DB-->>INV: inventory balance
    INV-->>A: 201 balance
```

## 3. Inventory update flow

```mermaid
sequenceDiagram
    autonumber
    actor A as Admin/Warehouse
    participant I as InventoryController
    participant W as WarehouseController
    participant DB as Database

    A->>W: GET /warehouses
    W->>DB: select warehouses
    DB-->>W: list
    W-->>A: 200 OK

    A->>I: POST /inventories
    I->>DB: insert/update inventory record
    DB-->>I: inventory
    I-->>A: 201 Created

    A->>I: PUT /inventories/{id}
    I->>DB: update inventory
    DB-->>I: inventory
    I-->>A: 200 OK
```

## Viewing these diagrams

- GitHub, GitLab, and most Markdown viewers render Mermaid natively.
- Swagger UI does **not** render Mermaid out of the box. To keep diagrams alongside the API, paste the Mermaid block into an operation's `@OA\...` `description` field; it will display as a code block in Swagger UI.
