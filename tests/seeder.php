<?php

/**
 * Builds an SQLite database with the standard AuraTech/SalePro table layout and
 * ~13 months of realistic, invented data for a nuts & dried-fruit shop.
 * Used by tests/run.php and by the demo builder. No real customer data.
 */

namespace AuraTech\SmartDashboard\Tests;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

class Seeder
{
    private PDO $pdo;
    private int $seed = 42;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    private function rnd(): float
    {
        // deterministic LCG
        $this->seed = ($this->seed * 1103515245 + 12345) & 0x7fffffff;
        return $this->seed / 0x7fffffff;
    }

    private function pick(array $a) { return $a[(int) floor($this->rnd() * count($a)) % count($a)]; }

    private function weighted(array $weights)
    {
        $t = array_sum($weights); $r = $this->rnd() * $t;
        foreach ($weights as $k => $w) { if (($r -= $w) <= 0) return $k; }
        return array_key_last($weights);
    }

    public function schema(): void
    {
        $this->pdo->exec(<<<SQL
CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, role_id INTEGER, is_active INTEGER DEFAULT 1);
CREATE TABLE warehouses (id INTEGER PRIMARY KEY, name TEXT);
CREATE TABLE categories (id INTEGER PRIMARY KEY, name TEXT);
CREATE TABLE products (id INTEGER PRIMARY KEY, name TEXT, code TEXT, category_id INTEGER, qty REAL, alert_quantity REAL, cost REAL, price REAL, is_active INTEGER DEFAULT 1);
CREATE TABLE product_batches (id INTEGER PRIMARY KEY, product_id INTEGER, batch_no TEXT, qty REAL, expired_date TEXT);
CREATE TABLE customers (id INTEGER PRIMARY KEY, name TEXT, phone_number TEXT);
CREATE TABLE suppliers (id INTEGER PRIMARY KEY, name TEXT, company_name TEXT);
CREATE TABLE tables (id INTEGER PRIMARY KEY, name TEXT);
CREATE TABLE sales (id INTEGER PRIMARY KEY, reference_no TEXT, user_id INTEGER, customer_id INTEGER, warehouse_id INTEGER, table_id INTEGER,
  grand_total REAL, paid_amount REAL, sale_status INTEGER, payment_status INTEGER, created_at TEXT);
CREATE INDEX sales_date ON sales(created_at);
CREATE TABLE product_sales (id INTEGER PRIMARY KEY, sale_id INTEGER, product_id INTEGER, qty REAL, net_unit_price REAL, total REAL);
CREATE INDEX ps_sale ON product_sales(sale_id);
CREATE TABLE purchases (id INTEGER PRIMARY KEY, reference_no TEXT, user_id INTEGER, supplier_id INTEGER, warehouse_id INTEGER, grand_total REAL, paid_amount REAL, created_at TEXT);
CREATE TABLE returns (id INTEGER PRIMARY KEY, reference_no TEXT, user_id INTEGER, customer_id INTEGER, warehouse_id INTEGER, grand_total REAL, created_at TEXT);
CREATE TABLE quotations (id INTEGER PRIMARY KEY, reference_no TEXT, user_id INTEGER, customer_id INTEGER, grand_total REAL, quotation_status INTEGER, created_at TEXT);
CREATE TABLE payments (id INTEGER PRIMARY KEY, payment_reference TEXT, user_id INTEGER, sale_id INTEGER, purchase_id INTEGER, amount REAL, paying_method TEXT, created_at TEXT);
CREATE TABLE expense_categories (id INTEGER PRIMARY KEY, name TEXT);
CREATE TABLE expenses (id INTEGER PRIMARY KEY, expense_category_id INTEGER, user_id INTEGER, warehouse_id INTEGER, amount REAL, created_at TEXT);
CREATE TABLE deliveries (id INTEGER PRIMARY KEY, reference_no TEXT, sale_id INTEGER, address TEXT, status INTEGER, created_at TEXT);
CREATE TABLE cheques (id INTEGER PRIMARY KEY, cheque_no TEXT, payee TEXT, amount REAL, due_date TEXT, status INTEGER);
CREATE TABLE sd_layouts (id INTEGER PRIMARY KEY, scope TEXT, scope_id TEXT, layout TEXT, updated_by INTEGER, created_at TEXT, updated_at TEXT, UNIQUE(scope, scope_id));
CREATE TABLE sd_settings (setting_key TEXT PRIMARY KEY, value TEXT, created_at TEXT, updated_at TEXT);
SQL);
    }

    public function seed(DateTimeImmutable $now): void
    {
        $this->schema();
        $pdo = $this->pdo;
        $pdo->beginTransaction();
        $tz = new DateTimeZone('Asia/Jerusalem');
        $now = $now->setTimezone($tz);

        $ins = fn (string $t, array $row) => $pdo->prepare("INSERT INTO $t (" . implode(',', array_keys($row)) . ') VALUES (' . implode(',', array_fill(0, count($row), '?')) . ')')->execute(array_values($row));

        foreach ([[1, 'מנהל ראשי', 1], [2, 'קופה 1', 4], [3, 'קופה 2', 4], [4, 'סניף צפון', 4], [5, 'שירות עצמי', 5]] as [$id, $n, $r]) $ins('users', ['id' => $id, 'name' => $n, 'role_id' => $r]);
        $ins('warehouses', ['id' => 1, 'name' => 'חנות ראשית']);
        $ins('warehouses', ['id' => 2, 'name' => 'סניף צפון']);

        $cats = [1 => 'אגוזים', 2 => 'פיצוחים', 3 => 'פירות יבשים', 4 => 'תבלינים', 5 => 'מתוקים', 6 => 'קפה ומשקאות'];
        foreach ($cats as $id => $n) $ins('categories', ['id' => $id, 'name' => $n]);

        // name, category, price per unit (kg or unit), weight? , popularity
        $catalog = [
            ['קשיו קלוי', 1, 89, true, 9], ['שקדים טבעי', 1, 69, true, 7], ['אגוזי מלך', 1, 75, true, 6], ['פיסטוק קלוי', 1, 129, true, 7],
            ['פקאן', 1, 119, true, 4], ['לוז קלוי', 1, 79, true, 3], ['מקדמיה', 1, 159, true, 2], ['ברזיל', 1, 99, true, 2],
            ['בוטן אמריקאי', 2, 29, true, 8], ['בוטן קלוי', 2, 32, true, 8], ['גרעינים לבנים', 2, 39, true, 6], ['גרעינים שחורים', 2, 35, true, 5],
            ['גרעיני דלעת', 2, 49, true, 4], ['תערובת פיצוחים', 2, 55, true, 6], ['חומוס קלוי', 2, 25, true, 3],
            ['תמרים מג׳הול', 3, 59, true, 7], ['משמש מיובש', 3, 65, true, 4], ['צימוקים', 3, 29, true, 3], ['חמוציות', 3, 45, true, 3],
            ['תאנים מיובשות', 3, 69, true, 2], ['מנגו מיובש', 3, 89, true, 3], ['שזיף מיובש', 3, 39, true, 2],
            ['פפריקה מתוקה', 4, 12, false, 3], ['כמון טחון', 4, 14, false, 3], ['זעתר', 4, 18, false, 5], ['סומק', 4, 16, false, 2], ['בהרט', 4, 15, false, 2],
            ['חלבה', 5, 45, true, 4], ['בקלאווה', 5, 99, true, 4], ['מעמול', 5, 69, true, 3], ['סוכריות גומי', 5, 49, true, 3], ['שוקולד מריר', 5, 22, false, 3],
            ['קפה טורקי', 6, 24, false, 6], ['קפה עם הל', 6, 28, false, 5], ['תה צמחים', 6, 19, false, 2], ['מוחיטו קפוא', 6, 18, false, 6],
        ];
        $products = [];
        foreach ($catalog as $i => [$n, $c, $price, $weight, $pop]) {
            $id = $i + 1;
            $cost = round($price * (0.55 + $this->rnd() * 0.15), 2);
            $alert = $weight ? 5 : 10;
            $qty = round($alert * (0.3 + $this->rnd() * 5), 3);
            if (in_array($n, ['מקדמיה', 'חמוציות'], true)) $qty = 0;          // out of stock but still selling
            if (in_array($n, ['פיסטוק קלוי', 'זעתר', 'תמרים מג׳הול'], true)) $qty = round($alert * 0.4, 3);
            $ins('products', ['id' => $id, 'name' => $n, 'code' => (string) (1000 + $id * 7), 'category_id' => $c, 'qty' => $qty, 'alert_quantity' => $alert, 'cost' => $cost, 'price' => $price]);
            $products[$id] = compact('id', 'price', 'weight', 'pop') + ['cat' => $c];
        }
        // expiry batches
        foreach ([16 => 5, 29 => 9, 30 => 3, 17 => 20, 36 => 12, 28 => 40, 18 => -2] as $pid => $days) {
            $ins('product_batches', ['product_id' => $pid, 'batch_no' => 'B' . $pid . '-' . abs($days), 'qty' => round(2 + $this->rnd() * 10, 2), 'expired_date' => $now->modify("$days days")->format('Y-m-d')]);
        }

        $first = ['אחמד', 'סאמר', 'נור', 'רים', 'יוסף', 'מונא', 'עלי', 'דנה', 'ח׳אלד', 'לילא', 'עומר', 'רנא', 'אמיר', 'סלמא', 'פאדי', 'היבה', 'מוחמד', 'וסים', 'ג׳מיל', 'רולא'];
        $last = ['חסון', 'עבאס', 'נסר', 'חלבי', 'קאסם', 'זידאן', 'מרעי', 'סעד', 'חמדאן', 'עודה', 'פארס', 'טאפש'];
        $ins('customers', ['id' => 1, 'name' => 'לקוח כללי', 'phone_number' => '']);
        for ($i = 2; $i <= 70; $i++) $ins('customers', ['id' => $i, 'name' => $this->pick($first) . ' ' . $this->pick($last), 'phone_number' => '05' . (int) (10000000 + $this->rnd() * 89999999)]);
        $biz = [71 => 'מאפיית הכפר', 72 => 'קייטרינג אל-ג׳ליל', 73 => 'מרכול השכונה', 74 => 'בית קפה הגבעה', 75 => 'אולמי אירועים הדר'];
        foreach ($biz as $id => $n) $ins('customers', ['id' => $id, 'name' => $n, 'phone_number' => '04' . (int) (1000000 + $this->rnd() * 8999999)]);

        $suppliers = ['אגוזי הגליל בע״מ', 'פירות יבשים מרכז', 'תבליני הצפון', 'יבואני הדרום', 'ממתקי המזרח', 'קלייה ארצית', 'קפה ותבלין', 'אריזות ישראל', 'חקלאי העמק', 'הפצת מתוקים'];
        foreach ($suppliers as $i => $n) $ins('suppliers', ['id' => $i + 1, 'name' => $n, 'company_name' => $n]);
        for ($t = 1; $t <= 8; $t++) $ins('tables', ['id' => $t, 'name' => 'שולחן ' . $t]);

        $ecats = [1 => 'שכירות', 2 => 'משכורות', 3 => 'חשמל ומים', 4 => 'שיווק ופרסום', 5 => 'אחזקה', 6 => 'דלק והובלה', 7 => 'עמלות אשראי'];
        foreach ($ecats as $id => $n) $ins('expense_categories', ['id' => $id, 'name' => $n]);

        // --- sales -----------------------------------------------------------
        $start = $now->setTime(0, 0)->modify('first day of this month')->modify('-13 months');
        $popIds = [];
        foreach ($products as $p) for ($k = 0; $k < $p['pop']; $k++) $popIds[] = $p['id'];
        $hourW = [8 => 2, 9 => 5, 10 => 8, 11 => 9, 12 => 10, 13 => 8, 14 => 6, 15 => 6, 16 => 8, 17 => 10, 18 => 11, 19 => 9, 20 => 6, 21 => 3];
        $dayW = [0 => 1.0, 1 => 0.8, 2 => 0.85, 3 => 0.9, 4 => 1.25, 5 => 1.45, 6 => 0.7];   // Sun..Sat
        $methods = ['Cash' => 38, 'Credit Card' => 44, 'Bit' => 9, 'Cibus' => 3, 'Cheque' => 4, 'Gift Card' => 2];
        $saleId = 0; $payId = 0; $ref = 94000;
        $psStmt = $pdo->prepare('INSERT INTO product_sales (sale_id, product_id, qty, net_unit_price, total) VALUES (?,?,?,?,?)');
        $sStmt = $pdo->prepare('INSERT INTO sales (id, reference_no, user_id, customer_id, warehouse_id, table_id, grand_total, paid_amount, sale_status, payment_status, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        $pStmt = $pdo->prepare('INSERT INTO payments (payment_reference, user_id, sale_id, purchase_id, amount, paying_method, created_at) VALUES (?,?,?,?,?,?,?)');

        for ($day = $start; $day <= $now; $day = $day->modify('+1 day')) {
            $monthsAgo = (int) (($now->getTimestamp() - $day->getTimestamp()) / (86400 * 30));
            $season = in_array((int) $day->format('n'), [3, 4, 9, 12], true) ? 1.25 : 1.0;  // holidays
            $growth = 1 + (13 - $monthsAgo) * 0.012;
            $n = (int) round((34 + $this->rnd() * 16) * $dayW[(int) $day->format('w')] * $season * $growth);
            if ($day->format('Y-m-d') === $now->modify('-1 day')->format('Y-m-d')) $n = (int) ($n * 1.1);
            for ($i = 0; $i < $n; $i++) {
                $h = $this->weighted($hourW);
                $ts = $day->setTime($h, (int) ($this->rnd() * 60), (int) ($this->rnd() * 60));
                if ($ts > $now) continue;
                $saleId++; $ref++;
                $isBiz = $this->rnd() < 0.05;
                $cust = $isBiz ? 71 + (int) ($this->rnd() * 5) : ($this->rnd() < 0.72 ? 1 : 2 + (int) ($this->rnd() * 69));
                $lines = $isBiz ? 4 + (int) ($this->rnd() * 6) : 1 + (int) ($this->rnd() * 4);
                $total = 0;
                $rows = [];
                for ($l = 0; $l < $lines; $l++) {
                    $pid = $this->pick($popIds); $p = $products[$pid];
                    $qty = $p['weight'] ? round((0.1 + $this->rnd() * 0.6) * ($isBiz ? 6 : 1), 3) : (1 + (int) ($this->rnd() * ($isBiz ? 12 : 3)));
                    $lt = round($qty * $p['price'], 2);
                    $rows[] = [$pid, $qty, $p['price'], $lt];
                    $total += $lt;
                }
                $total = round($total, 2);
                $status = 1; $paid = $total; $pstat = 4;
                $ageDays = ($now->getTimestamp() - $ts->getTimestamp()) / 86400;
                if ($isBiz && $this->rnd() < 0.55) { $paid = round($total * ($this->rnd() < 0.5 ? 0 : 0.5), 2); $pstat = $paid > 0 ? 3 : 2; }
                if ($ageDays < 6 && $this->rnd() < 0.015) { $status = $this->rnd() < 0.5 ? 5 : 2; $paid = 0; $pstat = 2; }
                $table = null;
                if ($ageDays < 0.12 && $this->rnd() < 0.35) { $status = 2; $paid = 0; $pstat = 2; $table = 1 + (int) ($this->rnd() * 8); }
                $user = $this->weighted([2 => 45, 3 => 35, 4 => 15, 5 => 5]);
                $sStmt->execute([$saleId, 'TIN-' . $ref, $user, $cust, $user === 4 ? 2 : 1, $table, $total, $paid, $status, $pstat, $ts->format('Y-m-d H:i:s')]);
                foreach ($rows as [$pid, $qty, $pr, $lt]) $psStmt->execute([$saleId, $pid, $qty, $pr, $lt]);
                if ($paid > 0) {
                    $payId++;
                    $pStmt->execute(['SPR-' . $payId, $user, $saleId, null, $paid, $isBiz ? $this->weighted(['Cheque' => 50, 'Deposit' => 30, 'Credit Card' => 20]) : $this->weighted($methods), $ts->format('Y-m-d H:i:s')]);
                }
                if ($this->rnd() < 0.02 && $ageDays > 1) {
                    $ins('returns', ['reference_no' => 'RT-' . $saleId, 'user_id' => $user, 'customer_id' => $cust, 'warehouse_id' => 1, 'grand_total' => round($total * (0.3 + $this->rnd() * 0.7), 2), 'created_at' => $ts->modify('+1 day')->format('Y-m-d H:i:s')]);
                }
                if ($isBiz && $this->rnd() < 0.6) {
                    $dStatus = $ageDays > 2 ? 3 : ($ageDays > 0.5 ? 2 : 1);
                    if ($ageDays > 2 && $ageDays < 5 && $this->rnd() < 0.3) $dStatus = 2;
                    $ins('deliveries', ['reference_no' => 'DL-' . $saleId, 'sale_id' => $saleId, 'address' => 'חורפיש', 'status' => $dStatus, 'created_at' => $ts->format('Y-m-d H:i:s')]);
                }
            }
        }

        // --- purchases (weekly per active supplier) ---------------------------
        $purId = 0;
        for ($day = $start; $day <= $now; $day = $day->modify('+1 day')) {
            if ((int) $day->format('w') !== 1 && (int) $day->format('w') !== 3) continue;
            foreach ([1, 2, 3, 5, 6, 7] as $sup) {
                if ($this->rnd() < 0.55) continue;
                $purId++;
                $amt = round(1500 + $this->rnd() * 9000, 2);
                $ts = $day->setTime(9 + (int) ($this->rnd() * 6), (int) ($this->rnd() * 59));
                $age = ($now->getTimestamp() - $ts->getTimestamp()) / 86400;
                $paid = $age > 75 ? $amt : ($this->rnd() < 0.5 ? $amt : round($amt * ($this->rnd() < 0.5 ? 0 : 0.4), 2));
                $ins('purchases', ['id' => $purId, 'reference_no' => 'PR-' . (5000 + $purId), 'user_id' => 1, 'supplier_id' => $sup, 'warehouse_id' => 1, 'grand_total' => $amt, 'paid_amount' => $paid, 'created_at' => $ts->format('Y-m-d H:i:s')]);
                if ($paid > 0) { $payId++; $ins('payments', ['payment_reference' => 'PPR-' . $payId, 'user_id' => 1, 'sale_id' => null, 'purchase_id' => $purId, 'amount' => $paid, 'paying_method' => $this->weighted(['Cheque' => 55, 'Deposit' => 35, 'Cash' => 10]), 'created_at' => $ts->modify('+' . (int) ($this->rnd() * 10) . ' days')->format('Y-m-d H:i:s')]); }
            }
        }

        // --- expenses -------------------------------------------------------
        for ($m = $start; $m <= $now; $m = $m->modify('+1 month')) {
            foreach ([1 => 14000, 2 => 26000, 3 => 3200, 4 => 2500, 5 => 1200, 6 => 1800, 7 => 2100] as $cat => $base) {
                $ts = $m->modify('+' . (int) ($this->rnd() * 9) . ' days')->setTime(10, 0);
                if ($ts > $now) continue;
                $ins('expenses', ['expense_category_id' => $cat, 'user_id' => 1, 'warehouse_id' => 1, 'amount' => round($base * (0.85 + $this->rnd() * 0.3), 2), 'created_at' => $ts->format('Y-m-d H:i:s')]);
            }
        }

        // --- quotations, cheques ------------------------------------------------
        for ($q = 1; $q <= 40; $q++) {
            $ts = $now->modify('-' . (int) ($this->rnd() * 60) . ' days');
            $ins('quotations', ['reference_no' => 'QT-' . (300 + $q), 'user_id' => 1, 'customer_id' => 71 + $q % 5, 'grand_total' => round(800 + $this->rnd() * 6000, 2), 'quotation_status' => $this->rnd() < 0.6 ? 1 : 2, 'created_at' => $ts->format('Y-m-d H:i:s')]);
        }
        for ($c = 1; $c <= 18; $c++) {
            $ins('cheques', ['cheque_no' => (string) (70100 + $c), 'payee' => $suppliers[$c % count($suppliers)], 'amount' => round(2000 + $this->rnd() * 15000, 2), 'due_date' => $now->modify('+' . (int) ($c * 2.5 - 8) . ' days')->format('Y-m-d'), 'status' => $c <= 4 ? 2 : 1]);
        }
        $pdo->commit();
    }
}
