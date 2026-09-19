<?php

// Who the documents are from. Everything printed on an invoice, a statement or
// a report letterhead comes from here, so the office changes a phone number in
// one place (the .env file) and not in seven templates.
return [
    'name' => env('COMPANY_NAME', 'الساحل لمستلزمات المقاهي'),
    'tagline' => env('COMPANY_TAGLINE', 'توريد مستلزمات المقاهي بالجملة'),
    'address' => env('COMPANY_ADDRESS', 'طرابلس، ليبيا'),
    'phone' => env('COMPANY_PHONE', '091-0000000'),
    'email' => env('COMPANY_EMAIL', 'info@cafe-supply.ly'),
    // Printed only when set.
    'commercial_register' => env('COMPANY_COMMERCIAL_REGISTER'),
    'tax_number' => env('COMPANY_TAX_NUMBER'),
    'currency' => 'د.ل',
    // A line at the foot of every invoice, e.g. the returns policy.
    'invoice_terms' => env('COMPANY_INVOICE_TERMS', 'تُراجَع البضاعة عند الاستلام. لا تُقبل المرتجعات بعد التوقيع إلا لعيب مصنعي.'),
];
