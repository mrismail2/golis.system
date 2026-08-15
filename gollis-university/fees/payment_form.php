<?php
/** Fees - record a payment against an invoice. */

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_admin();

$feeId  = get_int('fee_id') ?: post_int('fee_id');
$errors = [];

$payment = [
    'receipt_no' => next_receipt_no(),
    'amount'     => '',
    'paid_on'    => date('Y-m-d'),
    'method'     => 'cash',
    'note'       => '',
];

$invoice = $feeId
    ? db_row(
        "SELECT f.*, s.reg_no, s.full_name, s.id AS student_id,
                COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.fee_id = f.id), 0) AS paid
         FROM fees f JOIN students s ON s.id = f.student_id
         WHERE f.id = ?",
        [$feeId]
    )
    : null;

if ($invoice) {
    $payment['amount'] = number_format(max(0, fee_balance((float)$invoice['amount'], (float)$invoice['paid'])), 2, '.', '');
}

if (is_post()) {
    verify_csrf();

    $payment['receipt_no'] = (string)input('receipt_no');
    $payment['amount']     = (string)input('amount');
    $payment['paid_on']    = (string)input('paid_on');
    $payment['method']     = (string)input('method', 'cash');
    $payment['note']       = (string)input('note');

    $amount = round((float)$payment['amount'], 2);

    if (!$invoice)                    { $errors[] = 'Choose the invoice being paid.'; }
    if ($payment['receipt_no'] === '') { $errors[] = 'The receipt number is required.'; }
    if ($amount <= 0)                  { $errors[] = 'Enter an amount greater than zero.'; }

    if ($invoice) {
        $balance = fee_balance((float)$invoice['amount'], (float)$invoice['paid']);
        if ($amount > $balance + 0.001) {
            $errors[] = 'That is more than the outstanding balance of ' . money($balance) . '.';
        }
    }

    if (!$errors) {
        try {
            db_query(
                "INSERT INTO payments (fee_id, receipt_no, amount, paid_on, method, note, recorded_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    $feeId, $payment['receipt_no'], $amount, $payment['paid_on'] ?: date('Y-m-d'),
                    $payment['method'], $payment['note'] ?: null, user_id(),
                ]
            );
            flash('Payment of ' . money($amount) . ' recorded, receipt ' . $payment['receipt_no'] . '.');
            redirect('fees/statement.php?student_id=' . (int)$invoice['student_id']);
        } catch (PDOException $exception) {
            $errors[] = $exception->getCode() === '23000'
                ? 'Receipt number ' . $payment['receipt_no'] . ' has already been used.'
                : 'The payment could not be saved: ' . $exception->getMessage();
        }
    }
}

$openInvoices = db_all(
    "SELECT f.id,
            CONCAT(s.reg_no, ' - ', s.full_name, ' · ', f.academic_year, ' S', f.semester,
                   ' · ', UPPER(LEFT(f.fee_type,1)), SUBSTRING(f.fee_type,2)) AS label
     FROM fees f
     JOIN students s ON s.id = f.student_id
     WHERE f.amount > COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.fee_id = f.id), 0)
     ORDER BY s.reg_no"
);

$pageTitle    = 'Record payment';
$pageSubtitle = $invoice
    ? $invoice['full_name'] . ' · ' . $invoice['reg_no'] . ' · balance '
      . money(fee_balance((float)$invoice['amount'], (float)$invoice['paid']))
    : 'Choose an invoice with an outstanding balance';
$pageActions  = '<a class="btn-sm btn-ghost" href="' . url('fees/index.php') . '">← Back to fees</a>';

require_once APP_ROOT . '/includes/header.php';
?>

<div class="panel" style="max-width:820px">
  <?php foreach ($errors as $error): ?>
    <div class="alert error"><?= e($error) ?></div>
  <?php endforeach; ?>

  <?php if (!$openInvoices && !$invoice): ?>
    <div class="alert info">Every invoice has been paid in full.</div>
  <?php endif; ?>

  <form method="get" class="filters" style="margin-bottom:18px">
    <div class="field" style="min-width:340px">
      <label for="fee_id">Invoice</label>
      <select id="fee_id" name="fee_id" onchange="this.form.submit()">
        <option value="">Choose an invoice</option>
        <?= options($openInvoices, 'id', 'label', $feeId ?: null) ?>
      </select>
    </div>
  </form>

  <?php if ($invoice): ?>
    <?php $balance = fee_balance((float)$invoice['amount'], (float)$invoice['paid']); ?>

    <dl class="data-list" style="margin-bottom:18px">
      <div><dt>Student</dt><dd><?= e($invoice['full_name']) ?> (<?= e($invoice['reg_no']) ?>)</dd></div>
      <div><dt>Term</dt><dd><?= e($invoice['academic_year']) ?> · Semester <?= (int)$invoice['semester'] ?></dd></div>
      <div><dt>Invoice amount</dt><dd><?= money($invoice['amount']) ?> (<?= e($invoice['fee_type']) ?>)</dd></div>
      <div><dt>Outstanding balance</dt><dd><strong><?= money($balance) ?></strong></dd></div>
    </dl>

    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="fee_id" value="<?= (int)$invoice['id'] ?>">

      <div class="form-grid three">
        <div class="field">
          <label for="receipt_no">Receipt number</label>
          <input id="receipt_no" name="receipt_no" value="<?= e($payment['receipt_no']) ?>" maxlength="25" required>
        </div>
        <div class="field">
          <label for="amount">Amount (<?= e(APP_CURRENCY) ?>)</label>
          <input id="amount" name="amount" type="number" step="0.01" min="0.01" max="<?= e(number_format($balance, 2, '.', '')) ?>"
                 value="<?= e($payment['amount']) ?>" required>
        </div>
        <div class="field">
          <label for="paid_on">Paid on</label>
          <input id="paid_on" name="paid_on" type="date" value="<?= e($payment['paid_on']) ?>">
        </div>
      </div>

      <div class="form-grid">
        <div class="field">
          <label for="method">Payment method</label>
          <select id="method" name="method">
            <?php foreach (['cash' => 'Cash', 'zaad' => 'Zaad', 'edahab' => 'eDahab', 'bank' => 'Bank transfer', 'cheque' => 'Cheque'] as $value => $label): ?>
              <option value="<?= $value ?>" <?= $payment['method'] === $value ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="note">Note</label>
          <input id="note" name="note" value="<?= e($payment['note']) ?>" maxlength="160" placeholder="Optional">
        </div>
      </div>

      <div class="form-actions">
        <button class="btn-sm btn-blue" type="submit">Record payment</button>
        <a class="btn-sm btn-ghost" href="<?= url('fees/statement.php?student_id=' . (int)$invoice['student_id']) ?>">Student statement</a>
      </div>
    </form>
  <?php endif; ?>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
