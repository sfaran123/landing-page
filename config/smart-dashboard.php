<?php

/*
|--------------------------------------------------------------------------
| AuraTech Smart Dashboard – configuration
|--------------------------------------------------------------------------
| Everything that is specific to one installation lives here, so the module
| can be dropped into every AuraTech customer system without code changes.
| After editing, run:  php artisan smart-dashboard:doctor
*/

return [

    /* ---------------------------------------------------------------- *
     | Routing / UI
     * ---------------------------------------------------------------- */
    'route_prefix' => 'smart-dashboard',          // /smart-dashboard, /smart-dashboard/editor
    'middleware'   => ['web', 'auth'],
    'replace_default_dashboard' => false,         // true => /dashboard redirects to the smart dashboard

    // Blade layout to embed in. null = the module's own full-page shell.
    // Existing AuraTech layout is usually 'backend.layout.main' with section 'content'.
    'extends' => null,
    'section' => 'content',

    'locale'    => 'he',
    'direction' => 'rtl',
    'currency'  => 'ILS',
    'timezone'  => env('APP_TIMEZONE', 'Asia/Jerusalem'),
    'week_starts_on' => 0,                        // 0 = Sunday (Israel)

    /* ---------------------------------------------------------------- *
     | Data / performance
     * ---------------------------------------------------------------- */
    'connection' => null,                         // null = default DB connection
    'cache_ttl'  => 120,                          // seconds each widget result is cached
    'cache_store' => null,

    /* ---------------------------------------------------------------- *
     | Access control
     * ---------------------------------------------------------------- */
    // role_id values (users.role_id) allowed to open the layout editor
    'editor_role_ids' => [1],
    // when true, a widget with a 'permission' is shown only if $user->can($permission)
    'enforce_widget_permissions' => false,
    // every user may arrange a personal dashboard (hide / move / add widgets for himself)
    'allow_personal_layouts' => true,
    // role_id values that ALWAYS see only their own sales (e.g. cashiers). [] = everyone may pick "all users"
    'own_data_role_ids' => [],
    // show real exception messages inside widgets (never enable in production)
    'debug' => env('APP_DEBUG', false),

    /* ---------------------------------------------------------------- *
     | Business types – each has its own default layout (see Presets.php)
     * ---------------------------------------------------------------- */
    'business_types' => [
        'retail'      => ['label' => 'קמעונאות / חנות',        'icon' => 'store'],
        'grocery'     => ['label' => 'מזון, פיצוחים ומכולת',    'icon' => 'basket'],
        'restaurant'  => ['label' => 'מסעדה / בית קפה',         'icon' => 'cup'],
        'wholesale'   => ['label' => 'סיטונאות והפצה',          'icon' => 'truck'],
        'ecommerce'   => ['label' => 'מסחר אלקטרוני',           'icon' => 'globe'],
        'services'    => ['label' => 'שירותים',                 'icon' => 'briefcase'],
    ],
    'default_business_type' => 'grocery',

    /* ---------------------------------------------------------------- *
     | Database map – the ONLY place that knows table / column names.
     | Defaults match the standard AuraTech (SalePro based) schema.
     | 'where' adds fixed conditions, e.g. installations that store
     | purchases inside the sales table:
     |    'purchases' => ['table' => 'sales', 'where' => ['transaction_type' => 'purchase'], ...]
     * ---------------------------------------------------------------- */
    'schema' => [
        'sales' => [
            'table' => 'sales', 'date' => 'created_at', 'total' => 'grand_total', 'paid' => 'paid_amount',
            'customer_id' => 'customer_id', 'user_id' => 'user_id', 'warehouse_id' => 'warehouse_id',
            'reference' => 'reference_no', 'status' => 'sale_status', 'payment_status' => 'payment_status',
            'table_id' => 'table_id',
            'draft_status' => [5], 'pending_status' => [2], 'cancelled_status' => [],
            'statuses' => [1 => 'הושלם', 2 => 'ממתין', 3 => 'בוטל', 4 => 'הוחזר', 5 => 'טיוטה'],
            'where' => [],
        ],
        'product_sales' => [
            'table' => 'product_sales', 'sale_id' => 'sale_id', 'product_id' => 'product_id',
            'qty' => 'qty', 'total' => 'total',
        ],
        'purchases' => [
            'table' => 'purchases', 'date' => 'created_at', 'total' => 'grand_total', 'paid' => 'paid_amount',
            'supplier_id' => 'supplier_id', 'user_id' => 'user_id', 'warehouse_id' => 'warehouse_id',
            'reference' => 'reference_no', 'where' => [],
        ],
        'returns' => [
            'table' => 'returns', 'date' => 'created_at', 'total' => 'grand_total',
            'customer_id' => 'customer_id', 'user_id' => 'user_id', 'warehouse_id' => 'warehouse_id',
            'reference' => 'reference_no', 'where' => [],
        ],
        'quotations' => [
            'table' => 'quotations', 'date' => 'created_at', 'total' => 'grand_total',
            'customer_id' => 'customer_id', 'user_id' => 'user_id', 'reference' => 'reference_no',
            'status' => 'quotation_status', 'statuses' => [1 => 'ממתינה', 2 => 'נשלחה'], 'where' => [],
        ],
        'payments' => [
            'table' => 'payments', 'date' => 'created_at', 'amount' => 'amount', 'method' => 'paying_method',
            'sale_id' => 'sale_id', 'purchase_id' => 'purchase_id', 'user_id' => 'user_id',
            'reference' => 'payment_reference', 'where' => [],
        ],
        'expenses' => [
            'table' => 'expenses', 'date' => 'created_at', 'amount' => 'amount',
            'category_id' => 'expense_category_id', 'user_id' => 'user_id', 'warehouse_id' => 'warehouse_id',
            'where' => [],
        ],
        'expense_categories' => ['table' => 'expense_categories', 'name' => 'name'],
        'products' => [
            'table' => 'products', 'name' => 'name', 'code' => 'code', 'qty' => 'qty',
            'alert_quantity' => 'alert_quantity', 'cost' => 'cost', 'price' => 'price',
            'category_id' => 'category_id', 'is_active' => 'is_active',
        ],
        'categories' => ['table' => 'categories', 'name' => 'name'],
        'product_batches' => [
            'table' => 'product_batches', 'product_id' => 'product_id', 'qty' => 'qty',
            'expired_date' => 'expired_date', 'batch_no' => 'batch_no',
        ],
        'customers' => ['table' => 'customers', 'name' => 'name', 'phone' => 'phone_number'],
        'suppliers' => ['table' => 'suppliers', 'name' => 'name', 'company' => 'company_name'],
        'roles'     => ['table' => 'roles', 'name' => 'name'],
        'users'     => ['table' => 'users', 'name' => 'name', 'role_id' => 'role_id', 'is_active' => 'is_active'],
        'warehouses'=> ['table' => 'warehouses', 'name' => 'name'],
        'deliveries'=> [
            'table' => 'deliveries', 'date' => 'created_at', 'sale_id' => 'sale_id', 'status' => 'status',
            'reference' => 'reference_no', 'address' => 'address',
            // status value => label
            'statuses' => [1 => 'באריזה', 2 => 'במשלוח', 3 => 'נמסר'],
        ],
        // Cheques module is AuraTech-specific – adjust columns to your table.
        'cheques' => [
            'table' => 'cheques', 'amount' => 'amount', 'due_date' => 'due_date', 'number' => 'cheque_no',
            'party' => 'payee', 'status' => 'status', 'open_status' => [0, 1],
        ],
        'tables' => ['table' => 'tables', 'name' => 'name'],
    ],

    // Human labels for payments.paying_method values
    'payment_methods' => [
        'Cash' => 'מזומן', 'Credit Card' => 'כרטיס אשראי', 'Cheque' => 'צ׳ק', 'Gift Card' => 'כרטיס מתנה',
        'Deposit' => 'הפקדה', 'Bit' => 'ביט', 'Cibus' => 'סיבוס', 'Credit Note' => 'שובר זיכוי', 'Points' => 'נקודות',
    ],

    /* ---------------------------------------------------------------- *
     | Quick actions (preserves the existing top action bar)
     * ---------------------------------------------------------------- */
    'quick_actions' => [
        ['label' => 'קופה',          'url' => '/pos',   /* verify: POS route */                           'icon' => 'pos',      'color' => 'blue'],
        ['label' => 'הוסף חשבונית',  'url' => '/sales/create?sale_type=7',                                'icon' => 'invoice',  'color' => 'indigo'],
        ['label' => 'הוסף הזמנה',    'url' => '/sales/create?sale_type=1',                                'icon' => 'cart',     'color' => 'green'],
        ['label' => 'הוסף משלוח',    'url' => '/sales/create?sale_type=2',                                'icon' => 'truck',    'color' => 'orange'],
        ['label' => 'תשלום לספק',    'url' => '/sales/create?sale_type=32&transaction_type=purchase',     'icon' => 'wallet',   'color' => 'purple'],
        ['label' => 'הוסף לקוח',     'url' => '/customer/create',                                         'icon' => 'user-plus','color' => 'teal'],
        ['label' => 'הוסף ספק',      'url' => '/supplier/create',                                         'icon' => 'factory',  'color' => 'red'],
        ['label' => 'הוסף הוצאה',    'url' => '/expenses/create',                                         'icon' => 'receipt',  'color' => 'slate'],
    ],

    /* ---------------------------------------------------------------- *
     | Command palette (Ctrl+K) – jump to any screen of the system
     * ---------------------------------------------------------------- */
    'palette' => [
        ['רשימת מוצרים', '/products', 'מוצר'], ['הוסף מוצר', '/products/create', 'מוצר'],
        ['קטגוריות', '/category', 'מוצר'], ['הדפסת ברקוד', '/products/print_barcode', 'מוצר'],
        ['ספירת מלאי', '/stock-count', 'מוצר'], ['התאמות מלאי', '/qty_adjustment', 'מוצר'],
        ['לקוחות', '/customer', 'לקוח'], ['הוסף לקוח', '/customer/create', 'לקוח'],
        ['רשימת מכירות', '/sales', 'מכירה'], ['הוסף מכירה', '/sales/create?transaction_type=sale', 'מכירה'],
        ['רשימת חוסרים', '/sales/missing', 'מכירה'], ['טיוטות מכירה', '/sales?sale_status=5', 'מכירה'],
        ['ליקוט הזמנות', '/order-picking', 'מכירה'], ['משלוחים', '/delivery', 'מכירה'], ['שליחים', '/couriers', 'מכירה'],
        ['כרטיסי מתנה', '/gift_cards', 'מכירה'], ['קופונים', '/coupons', 'מכירה'],
        ['רשימת רכש', '/sales?transaction_type=purchase', 'רכש'], ['הוסף רכש', '/sales/create?transaction_type=purchase', 'רכש'],
        ['ספקים', '/supplier', 'רכש'], ['צ׳קים לספקים', '/cheques-dashboard?tab=list', 'רכש'],
        ['הצעות מחיר', '/quotations', 'הצעת מחיר'], ['הוסף הצעת מחיר', '/quotations/create', 'הצעת מחיר'],
        ['הוצאות', '/expenses', 'הוצאה'], ['הוסף הוצאה', '/expenses/create', 'הוצאה'],
        ['הכנסות', '/incomes', 'הכנסות'], ['העברות', '/transfers', 'העברה'],
        ['החזרת מכירה', '/return-sale', 'החזרה'], ['החזרת רכש', '/return-purchase', 'החזרה'],
        ['חשבונות בנק', '/accounts', 'בנק'], ['העברת כספים', '/money-transfers', 'בנק'], ['מאזן חשבונאי', '/accounts/balancesheet', 'בנק'],
        ['עובדים', '/employees', 'משאבי אנוש'], ['נוכחות', '/attendance', 'משאבי אנוש'], ['שכר', '/payroll', 'משאבי אנוש'],
        ['הפקדת כסף', '/money-deposits', 'תשלומים'],
        ['רווח והפסד', '/report/profit-and-loss', 'דוחות'], ['חובות לקוחות', '/report/accounts-receivable', 'דוחות'],
        ['יתרת ספקים', '/report/accounts-receivable-supplier', 'דוחות'], ['דוח הכנסות', '/report/income-statement', 'דוחות'],
        ['גרף הכנסות', '/report/revenue-chart', 'דוחות'], ['מלאי במחסן', '/report/warehouse_stock', 'דוחות'],
        ['מכירות עובדים', '/report/employee-sales', 'דוחות'], ['פג תוקף', '/report/product-expiry', 'דוחות'],
        ['התראת כמות', '/report/product_quantity_alert', 'דוחות'], ['יעד מכירות יומי', '/report/daily-sale-objective', 'דוחות'],
        ['לוח בקרה עסקי (ישן)', '/main-dashboard', 'דוחות'],
        ['משתמשים', '/user', 'הגדרות'], ['תפקידים והרשאות', '/role', 'הגדרות'], ['הגדרות כלליות', '/setting/general_setting', 'הגדרות'],
        ['מחסנים', '/warehouse', 'הגדרות'], ['שולחנות', '/tables', 'הגדרות'], ['מדפסות', '/printers', 'הגדרות'],
        ['תפריט דיגיטלי', '/digital-menu', 'הגדרות'], ['הזמנות שירות עצמי', '/self-service/orders', 'הגדרות'],
        ['גיבוי מסד נתונים', '/backup', 'הגדרות'],
    ],

    /* ---------------------------------------------------------------- *
     | Registered widgets. Add your own class here – it appears in the
     | editor library automatically.
     * ---------------------------------------------------------------- */
    'widgets' => [
        \AuraTech\SmartDashboard\Widgets\Providers\SmartInsights::class,
        \AuraTech\SmartDashboard\Widgets\Providers\QuickActions::class,
        \AuraTech\SmartDashboard\Widgets\Providers\KpiRevenue::class,
        \AuraTech\SmartDashboard\Widgets\Providers\KpiProfit::class,
        \AuraTech\SmartDashboard\Widgets\Providers\KpiOrders::class,
        \AuraTech\SmartDashboard\Widgets\Providers\KpiAvgTicket::class,
        \AuraTech\SmartDashboard\Widgets\Providers\KpiCustomerDebt::class,
        \AuraTech\SmartDashboard\Widgets\Providers\KpiSupplierDebt::class,
        \AuraTech\SmartDashboard\Widgets\Providers\KpiExpenses::class,
        \AuraTech\SmartDashboard\Widgets\Providers\KpiPurchases::class,
        \AuraTech\SmartDashboard\Widgets\Providers\KpiReturns::class,
        \AuraTech\SmartDashboard\Widgets\Providers\SalesTarget::class,
        \AuraTech\SmartDashboard\Widgets\Providers\RevenueTrend::class,
        \AuraTech\SmartDashboard\Widgets\Providers\CashFlow::class,
        \AuraTech\SmartDashboard\Widgets\Providers\PeriodMix::class,
        \AuraTech\SmartDashboard\Widgets\Providers\RevenueCompare::class,
        \AuraTech\SmartDashboard\Widgets\Providers\SalesHeatmap::class,
        \AuraTech\SmartDashboard\Widgets\Providers\PaymentMethods::class,
        \AuraTech\SmartDashboard\Widgets\Providers\CategorySales::class,
        \AuraTech\SmartDashboard\Widgets\Providers\RecentTransactions::class,
        \AuraTech\SmartDashboard\Widgets\Providers\TopProducts::class,
        \AuraTech\SmartDashboard\Widgets\Providers\TopCustomers::class,
        \AuraTech\SmartDashboard\Widgets\Providers\DebtAging::class,
        \AuraTech\SmartDashboard\Widgets\Providers\LowStock::class,
        \AuraTech\SmartDashboard\Widgets\Providers\ExpiringProducts::class,
        \AuraTech\SmartDashboard\Widgets\Providers\EmployeeSales::class,
        \AuraTech\SmartDashboard\Widgets\Providers\OpenOrders::class,
        \AuraTech\SmartDashboard\Widgets\Providers\Deliveries::class,
        \AuraTech\SmartDashboard\Widgets\Providers\ChequesDue::class,
        \AuraTech\SmartDashboard\Widgets\Providers\QuotationsPipeline::class,
        \AuraTech\SmartDashboard\Widgets\Providers\ExpenseBreakdown::class,
        \AuraTech\SmartDashboard\Widgets\Providers\OpenTables::class,
    ],
];
