<?php
use App\Core\Util;

$fieldName = $fieldName ?? 'note';
$fieldId = $fieldId ?? 'note-' . bin2hex(random_bytes(3));
$noteValue = (string)($noteValue ?? '');
?>
<div class="html-note" data-html-note>
    <div class="html-note-head">
        <label for="<?= Util::e($fieldId) ?>-source">Note</label>
        <button type="button" class="tiny html-editor-toggle" data-html-editor-toggle>
            Editor Off
        </button>
    </div>

    <div class="html-editor-toolbar" data-html-toolbar>
        <button type="button" class="tiny" data-cmd="bold"><b>B</b></button>
        <button type="button" class="tiny" data-cmd="italic"><i>I</i></button>
        <button type="button" class="tiny" data-cmd="underline"><u>U</u></button>
        <button type="button" class="tiny" data-cmd="insertUnorderedList">• List</button>
        <button type="button" class="tiny" data-cmd="insertOrderedList">1. List</button>
        <button type="button" class="tiny" data-cmd="formatBlock" data-value="h3">H3</button>
        <button type="button" class="tiny" data-cmd="formatBlock" data-value="h4">H4</button>
        <button type="button" class="tiny" data-cmd="formatBlock" data-value="blockquote">Quote</button>
        <button type="button" class="tiny" data-cmd="createLink">Link</button>
        <button type="button" class="tiny" data-cmd="removeFormat">Clear</button>
    </div>

    <div
        class="html-editor"
        contenteditable="true"
        data-html-editor
        aria-label="HTML note editor"
    ><?= $noteValue ?></div>

    <textarea
        id="<?= Util::e($fieldId) ?>-source"
        class="html-source hidden"
        name="<?= Util::e($fieldName) ?>"
        rows="10"
        data-html-source
    ><?= Util::e($noteValue) ?></textarea>

    <div class="field-help">
        HTML note. Use the toolbar for rich text, or turn Editor Off to edit HTML source.
    </div>
</div>
