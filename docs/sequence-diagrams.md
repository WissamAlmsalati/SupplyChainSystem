# الساحل لمستلزمات المقاهي — Sequence Diagrams

These Mermaid diagrams describe the main business flows exposed by the API documented at `/api/documentation`.

## 1. Order checkout flow

```mermaid
sequenceDiagram
    autonumber
    actor U as Customer User
    participant C as CartController
    participant CI as CartItemController
    participant O as OrderController
    participant P as PaymentController
    participant DB as Database

    U->>C: POST /carts (create cart)
    C->>DB: insert cart
    DB-->>C: cart
    C-->>U: 201 Created

    loop Add items
        U->>CI: POST /cart-items
        CI->>DB: insert cart_item
        DB-->>CI: cart_item
        CI-->>U: 201 Created
    end

    U->>O: POST /orders (cart → order)
    O->>DB: insert order + order_items (transaction)
    DB-->>O: order
    O-->>U: 201 Created

    U->>P: POST /payments
    P->>DB: insert payment
    DB-->>P: payment
    P-->>U: 201 Created
```

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
