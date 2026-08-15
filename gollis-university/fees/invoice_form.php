<?php
/** Fees - raise or edit an invoice, or bill a whole department at once. */

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_admin();

$id      = get_int('id');
$errors  = [];
$invoice = [
    'student_id'    => (string)get_int('student_id') ?: '',
    'academic_year' => CURRENT_YEAR,
    'semester'      => CURRENT_SEMESTER,
    'fee_type'      => 'tuition',
    'amount'        => '220.00',
    'due_date'      => date('Y-m-d', strtotime('+30 days')),
    'description'   => 'Semester tuition',
];

if ($id) {
    $found = db_row("SELECT * FROM fees WHERE id = ?", [$id]);
    if (!$found) {
        flash('That invoice no longer exists.', 'error');
        redirect('fees/index.php');
    }
    $invoice = $found;
}

if (is_post()) {
    verify_csrf();

    $mode = (string)input('mode', 'single');

    $invoice['academic_year'] = (string)input('academic_year', CURRENT_YEAR);
    $invoice['semester']      = (int)input('semester', 1) === 2 ? 2 : 1;
    $invoice['fee_type']      = (string)input('fee_type', 'tuition');
    $invoice['amount']        = (string)input('amount');
    $invoice['due_date']      = (string)input('due_date');
    $invoice['description']   = (string)input('description');
    $invoice['student_id']    = (string)input('student_id');

    $amount = round((float)$invoice['amount'], 2);
    if ($amount <= 0) {
        $errors[] = 'Enter an amount greater than zero.';
    }

    if ($mode === 'bulk' && !$id) {
        // ---- bill every active student of a department / year ----
        $departmentId = get_int('department_id') ?: post_int('department_id');
        $yearOfStudy  = post_int('year_of_study');

        $sql    = "SELECT id FROM students WHERE status = 'active'";
        $params = [];
        if ($departmentId) {
            $sql .= " AND department_id = ?";
            $params[] = $departmentId;
        }
        if ($yearOfStudy) {
            $sql .= " AND year_of_study = ?";
            $params[] = $yearOfStudy;
        }
        $targets = array_column(db_all($sql, $params), 'id');

        if (!$targets) {
            $errors[] = 'No active students match that department and year.';
        }

        if (!$errors) {
            $created = 0;
            foreach ($targets as $studentId) {
                $stmt = db_query(
                    "INSERT IGNORE INTO fees (student_id, academic_year, semester, fee_type, amount, due_date, description)
                     VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [
                        (int)$studentId, $invoice['academic_year'], $invoice['semester'], $invoice['fee_type'],
                        $amount, $invoice['due_date'] ?: null, $invoice['description'] ?: null,
                    ]
                );
                $created += $stmt->rowCount();
            }

            flash($created . ' invoice' . ($created === 1 ? '' : 's') . ' created. Students who already had this '
                . $invoice['fee_type'] . ' invoice for the term were skipped.');
            redirect('fees/index.php?academic_year=' . urlencode($invoice['academic_year']) . '&semester=' . $invoice['semester']);
        }
    } else {
        // ---- a single invoice ----
        if ($invoice['student_id'] === '') {
            $errors[] = 'Choose the student to invoice.';
        }

        if (!$errors) {
            $fields = [
                (int)$invoice['student_id'],
                $invoice['academic_year'],
                $invoice['semester'],
                $invoice['fee_type'],
                $amount,
                $invoice['due_date'] ?: null,
                $invoice['description'] ?: null,
            ];

            try {
                if ($id) {
                    db_query(
                        "UPDATE fees SET student_id = ?, academic_year = ?, semester = ?, fee_type = ?,
                                amount = ?, due_date = ?, description = ?
                         WHERE id = ?",
                        [...$fields, $id]
                    );
                    flash('Invoice updated.');
                } else {
                    db_query(
                        "INSERT INTO fees (student_id, academic_year, semester, fee_type, amount, due_date, description)
                         VALUES (?, ?, ?, ?, ?, ?, ?)",
                        $fields
                    );
                    flash('Invoice created.');
                }

                redirect('fees/statement.php?student_id=' . (int)$invoice['student_id']);
            } catch (PDOException $exception) {
                $errors[] = $exception->getCode() === '23000'
                    ? 'This student already has a ' . $invoice['fee_type'] . ' invoice for that term.'
                    : 'The invoice could not be saved: ' . $exception->getMessage();
            }
        }
    }
}

$studentOptions = db_all(
    "SELECT id, CONCAT(reg_no, ' - ', full_name) AS label FROM students WHERE status = 'active' ORDER BY reg_no"
);

$pageTitle    = $id ? 'Edit invoice' : 'New invoice';
$pageSubtitle = 'Amounts in ' . APP_CURRENCY;
$pageActions  = '<a class="btn-sm btn-ghost" href="' . url('fees/index.php') . '">← Back to fees</a>';

require_once APP_ROOT . '/includes/header.php';
?>

<div class="panel" style="max-width:860px">
  <?php foreach ($errors as $error): ?>
    <div class="alert error"><?= e($error) ?></div>
  <?php endforeach; ?>

  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="mode" value="single">

    <div class="field">
      <label for="student_id">Student</label>
      <select id="student_id" name="student_id" required>
        <option value="">Choose a student</option>
        <?= options($studentOptions, 'id', 'label', $invoice['student_id']) ?>
      </select>
    </div>

    <div class="form-grid three">
      <div class="field">
        <label for="fee_type">Fee type</label>
        <select id="fee_type" name="fee_type">
          <?php foreach (['tuition', 'registration', 'exam', 'library', 'other'] as $type): ?>
            <option value="<?= $type ?>" <?= $invoice['fee_type'] === $type ? 'selected' : '' ?>><?= ucfirst($type) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="amount">Amount (<?= e(APP_CURRENCY) ?>)</label>
        <input id="amount" name="amount" type="number" step="0.01" min="0" value="<?= e($invoice['amount']) ?>" required>
        <span class="hint">Campus tuition normally ranges from $180 to $250 per semester.</span>
      </div>
      <div class="field">
        <label for="due_date">Due date</label>
        <input id="due_date" name="due_date" type="date" value="<?= e($invoice['due_date']) ?>">
      </div>
    </div>

    <div class="form-grid three">
      <div class="field">
        <label for="academic_year">Academic year</label>
        <select id="academic_year" name="academic_year">
          <?php foreach (academic_years() as $year): ?>
            <option value="<?= e($year) ?>" <?= $year === $invoice['academic_year'] ? 'selected' : '' ?>><?= e($year) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="semester">Semester</label>
        <select id="semester" name="semester">
          <?php foreach (semesters() as $value => $label): ?>
            <option value="<?= $value ?>" <?= (int)$invoice['semester'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="description">Description</label>
        <input id="description" name="description" value="<?= e($invoice['description']) ?>" maxlength="160">
      </div>
    </div>

    <div class="form-actions">
      <button class="btn-sm btn-blue" type="submit"><?= $id ? 'Save changes' : 'Create invoice' ?></button>
      <a class="btn-sm btn-ghost" href="<?= url('fees/index.php') ?>">Cancel</a>
    </div>
  </form>
</div>

<?php if (!$id): ?>
  <div class="panel" style="max-width:860px">
    <div class="panel-head">
      <div><h2>Bill a whole group</h2><p>Create the same invoice for every active student of a department and year</p></div>
    </div>

    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="mode" value="bulk">
      <input type="hidden" name="fee_type" value="tuition">

      <div class="form-grid three">
        <div class="field">
          <label for="bulk_department">Department</label>
          <select id="bulk_department" name="department_id">
            <option value="">All departments</option>
            <?= options(all_departments(), 'id', 'name') ?>
          </select>
        </div>
        <div class="field">
          <label for="bulk_year">Year of study</label>
          <select id="bulk_year" name="year_of_study">
            <option value="">All years</option>
            <?php for ($i = 1; $i <= 4; $i++): ?>
              <option value="<?= $i ?>">Year <?= $i ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="field">
          <label for="bulk_amount">Tuition amount (<?= e(APP_CURRENCY) ?>)</label>
          <input id="bulk_amount" name="amount" type="number" step="0.01" min="0" value="220.00" required>
        </div>
      </div>

      <div class="form-grid three">
        <div class="field">
          <label for="bulk_year_label">Academic year</label>
          <select id="bulk_year_label" name="academic_year">
            <?php foreach (academic_years() as $year): ?>
              <option value="<?= e($year) ?>" <?= $year === CURRENT_YEAR ? 'selected' : '' ?>><?= e($year) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="bulk_semester">Semester</label>
          <select id="bulk_semester" name="semester">
            <?php foreach (semesters() as $value => $label): ?>
              <option value="<?= $value ?>" <?= $value === CURRENT_SEMESTER ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="bulk_due">Due date</label>
          <input id="bulk_due" name="due_date" type="date" value="<?= e(date('Y-m-d', strtotime('+30 days'))) ?>">
        </div>
      </div>

      <div class="field">
        <label for="bulk_description">Description</label>
        <input id="bulk_description" name="description" value="Semester tuition" maxlength="160">
      </div>

      <div class="form-actions">
        <button class="btn-sm btn-gold" type="submit"
                data-confirm="Create this tuition invoice for every matching active student?">Create invoices</button>
      </div>
    </form>
  </div>
<?php endif; ?>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
