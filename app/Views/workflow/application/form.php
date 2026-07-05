<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <a href="<?= site_url('workflow/certification/' . $client['id']) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>
        Back to workflow
    </a>
    <a class="btn btn-outline-danger btn-sm" href="<?= site_url('workflow/certification/' . $client['id'] . '/documents/certification_application') ?>">
        <i class="fa-solid fa-file-pdf me-1" aria-hidden="true"></i>
        Certification Application PDF
    </a>
</div>

<form method="post" action="<?= site_url('workflow/certification/' . $client['id'] . '/application') ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div class="panel mb-3">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div>
                <div class="panel-title mb-1">Certification Application Form</div>
                <div class="text-secondary small">Application <?= esc($application['application_number']) ?></div>
            </div>
            <span class="badge text-bg-<?= ($application['status'] ?? '') === 'submitted' ? 'success' : 'secondary' ?>">
                <?= esc(ucwords(str_replace('_', ' ', $application['status'] ?? 'draft'))) ?>
            </span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
                <tbody>
                <tr>
                    <th class="table-light">Document Number</th>
                    <td><?= esc($application['document_number'] ?? 'F 25') ?></td>
                    <th class="table-light">Revision</th>
                    <td><?= esc($application['revision_number'] ?? '1') ?></td>
                    <th class="table-light">Issue</th>
                    <td><?= esc($application['issue_number'] ?? '2') ?></td>
                    <th class="table-light">Issue Date</th>
                    <td><?= esc($application['issue_date'] ?? '') ?></td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel mb-3">
        <div class="panel-title">Selected standards</div>
        <div class="row g-2">
            <?php foreach ($standards as $standard): ?>
                <div class="col-xl-3 col-lg-4 col-md-6">
                    <label class="border rounded p-2 w-100 h-100">
                        <input type="checkbox" name="standard_ids[]" value="<?= esc($standard['id']) ?>" <?= in_array((int) $standard['id'], $selectedStandardIds, true) ? 'checked' : '' ?>>
                        <span class="fw-semibold ms-1"><?= esc($standard['code']) ?></span>
                        <span class="text-secondary small d-block ms-4"><?= esc($standard['name']) ?></span>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="text-secondary small mt-2">Saving rebuilds the questionnaire from the selected standards and merges duplicate questions automatically.</div>
    </div>

    <?php foreach ($questionsBySection as $section => $questions): ?>
        <div class="panel mb-3">
            <div class="panel-title"><?= esc($section) ?></div>
            <div class="row g-3">
                <?php foreach ($questions as $question): ?>
                    <?php
                    $value = old('answers.' . $question['id'], $answers[(int) $question['id']] ?? '');
                    $rules = json_decode((string) ($question['validation_rules'] ?? ''), true) ?: [];
                    $options = $rules['options'] ?? [];
                    $required = (int) $question['mandatory'] === 1;
                    $fieldId = 'q' . $question['id'];
                    ?>
                    <div class="<?= $question['question_type'] === 'textarea' || $question['question_type'] === 'file' ? 'col-md-12' : 'col-md-6' ?>">
                        <label class="form-label" for="<?= esc($fieldId) ?>">
                            <?= esc($question['question_text']) ?>
                            <?php if ($required): ?><span class="text-danger">*</span><?php endif; ?>
                        </label>

                        <?php if ($question['question_type'] === 'textarea'): ?>
                            <textarea class="form-control" id="<?= esc($fieldId) ?>" name="answers[<?= esc($question['id']) ?>]" rows="3" <?= $required ? 'required' : '' ?>><?= esc($value) ?></textarea>
                        <?php elseif ($question['question_type'] === 'select'): ?>
                            <select class="form-select" id="<?= esc($fieldId) ?>" name="answers[<?= esc($question['id']) ?>]" <?= $required ? 'required' : '' ?>>
                                <option value="">Select</option>
                                <?php foreach ($options as $option): ?>
                                    <option value="<?= esc($option) ?>" <?= (string) $value === (string) $option ? 'selected' : '' ?>><?= esc($option) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($question['question_type'] === 'number'): ?>
                            <input class="form-control" id="<?= esc($fieldId) ?>" type="number" name="answers[<?= esc($question['id']) ?>]" value="<?= esc($value) ?>" <?= $required ? 'required' : '' ?>>
                        <?php elseif ($question['question_type'] === 'date'): ?>
                            <input class="form-control" id="<?= esc($fieldId) ?>" type="date" name="answers[<?= esc($question['id']) ?>]" value="<?= esc($value) ?>" <?= $required ? 'required' : '' ?>>
                        <?php elseif ($question['question_type'] === 'email'): ?>
                            <input class="form-control" id="<?= esc($fieldId) ?>" type="email" name="answers[<?= esc($question['id']) ?>]" value="<?= esc($value) ?>" <?= $required ? 'required' : '' ?>>
                        <?php elseif ($question['question_type'] === 'file'): ?>
                            <input class="form-control" id="<?= esc($fieldId) ?>" type="file" name="file_<?= esc($question['id']) ?>">
                            <?php if (! empty($attachmentsByQuestion[(int) $question['id']])): ?>
                                <div class="small text-secondary mt-1">
                                    Uploaded:
                                    <?= esc(implode(', ', array_map(static fn (array $file): string => $file['original_filename'], $attachmentsByQuestion[(int) $question['id']]))) ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <input class="form-control" id="<?= esc($fieldId) ?>" name="answers[<?= esc($question['id']) ?>]" value="<?= esc($value) ?>" <?= $required ? 'required' : '' ?>>
                        <?php endif; ?>

                        <?php if (($question['help_text'] ?? '') !== ''): ?>
                            <div class="form-text"><?= esc($question['help_text']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="panel mb-3">
        <div class="panel-title">Declaration</div>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="declaration_name">Submitted by</label>
                <input class="form-control" id="declaration_name" name="declaration_name" value="<?= esc(old('declaration_name', $application['declaration_name'] ?? ($client['contact_person'] ?? ''))) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="declaration_position">Position</label>
                <input class="form-control" id="declaration_position" name="declaration_position" value="<?= esc(old('declaration_position', $application['declaration_position'] ?? ($client['designation'] ?? ''))) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="declaration_date">Date</label>
                <input class="form-control" id="declaration_date" type="date" name="declaration_date" value="<?= esc(old('declaration_date', $application['declaration_date'] ?? date('Y-m-d'))) ?>">
            </div>
            <input type="hidden" name="cb_review_status" value="<?= esc($application['cb_review_status'] ?? '', 'attr') ?>">
            <input type="hidden" name="cb_review_notes" value="<?= esc($application['cb_review_notes'] ?? '', 'attr') ?>">
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mb-4">
        <button class="btn btn-outline-primary" type="submit" name="submit_application" value="0">
            <i class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></i>
            Save draft
        </button>
        <button class="btn btn-primary" type="submit" name="submit_application" value="1">
            <i class="fa-solid fa-paper-plane me-1" aria-hidden="true"></i>
            Submit application
        </button>
    </div>
</form>
<?= $this->endSection() ?>
