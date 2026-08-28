<?php
use App\Core\Util;

$fieldName = $fieldName ?? 'note';
$fieldId = $fieldId ?? 'note-' . bin2hex(random_bytes(3));
$noteValue = (string)($noteValue ?? '');
?>
<div class="html-note wp-note-editor" data-html-note>
    <div class="wp-note-editor-head">
        <label for="<?= Util::e($fieldId) ?>-source">Note</label>

        <div class="wp-note-mode-tabs" role="tablist" aria-label="Note editor mode">
            <button
                type="button"
                class="wp-note-mode-tab active"
                role="tab"
                aria-selected="true"
                data-note-mode="visual"
            >
                Visual
            </button>
            <button
                type="button"
                class="wp-note-mode-tab"
                role="tab"
                aria-selected="false"
                data-note-mode="html"
            >
                HTML
            </button>
        </div>
    </div>

    <div class="wp-note-editor-frame">
        <div class="wp-note-toolbar" data-html-toolbar>
            <select
                class="wp-note-format"
                data-note-format
                aria-label="Text format"
                title="Text format"
            >
                <option value="p">Paragraph</option>
                <option value="h3">Heading 3</option>
                <option value="h4">Heading 4</option>
                <option value="blockquote">Quote</option>
            </select>

            <span class="wp-note-toolbar-separator" aria-hidden="true"></span>

            <button
                type="button"
                class="wp-note-tool"
                data-cmd="bold"
                title="Bold"
                aria-label="Bold"
            ><b>B</b></button>

            <button
                type="button"
                class="wp-note-tool"
                data-cmd="italic"
                title="Italic"
                aria-label="Italic"
            ><i>I</i></button>

            <button
                type="button"
                class="wp-note-tool"
                data-cmd="underline"
                title="Underline"
                aria-label="Underline"
            ><u>U</u></button>

            <button
                type="button"
                class="wp-note-tool"
                data-cmd="strikeThrough"
                title="Strikethrough"
                aria-label="Strikethrough"
            ><s>S</s></button>

            <span class="wp-note-toolbar-separator" aria-hidden="true"></span>

            <button
                type="button"
                class="wp-note-tool wp-note-tool-wide"
                data-cmd="insertUnorderedList"
                title="Bulleted list"
                aria-label="Bulleted list"
            >• List</button>

            <button
                type="button"
                class="wp-note-tool wp-note-tool-wide"
                data-cmd="insertOrderedList"
                title="Numbered list"
                aria-label="Numbered list"
            >1. List</button>

            <button
                type="button"
                class="wp-note-tool"
                data-cmd="formatBlock"
                data-value="blockquote"
                title="Blockquote"
                aria-label="Blockquote"
            >❝</button>

            <span class="wp-note-toolbar-separator" aria-hidden="true"></span>

            <button
                type="button"
                class="wp-note-tool"
                data-note-link
                title="Insert/edit link"
                aria-label="Insert/edit link"
            >🔗</button>

            <button
                type="button"
                class="wp-note-tool"
                data-cmd="unlink"
                title="Remove link"
                aria-label="Remove link"
            >⌫</button>

            <button
                type="button"
                class="wp-note-tool"
                data-cmd="removeFormat"
                title="Clear formatting"
                aria-label="Clear formatting"
            >Tx</button>

            <span class="wp-note-toolbar-separator" aria-hidden="true"></span>

            <button
                type="button"
                class="wp-note-tool"
                data-cmd="undo"
                title="Undo"
                aria-label="Undo"
            >↶</button>

            <button
                type="button"
                class="wp-note-tool"
                data-cmd="redo"
                title="Redo"
                aria-label="Redo"
            >↷</button>
        </div>

        <div
            class="wp-note-linkbar hidden"
            data-note-linkbar
            aria-hidden="true"
        >
            <input
                type="url"
                placeholder="https://example.com"
                data-note-link-input
                aria-label="Link URL"
            >
            <label class="wp-note-link-check">
                <input type="checkbox" data-note-link-newtab>
                Open in new tab
            </label>
            <button
                type="button"
                class="tiny"
                data-note-link-apply
            >
                Apply
            </button>
            <button
                type="button"
                class="tiny"
                data-note-link-cancel
            >
                Cancel
            </button>
        </div>

        <div
            class="html-editor wp-note-visual"
            contenteditable="true"
            data-html-editor
            role="textbox"
            aria-multiline="true"
            aria-label="Visual HTML note editor"
        ><?= $noteValue ?></div>

        <textarea
            id="<?= Util::e($fieldId) ?>-source"
            class="html-source wp-note-source hidden"
            name="<?= Util::e($fieldName) ?>"
            rows="12"
            spellcheck="false"
            data-html-source
            aria-label="HTML source editor"
        ><?= Util::e($noteValue) ?></textarea>

        <div class="wp-note-statusbar">
            <span data-note-status>Visual editor</span>
            <span>HTML supported</span>
        </div>
    </div>

    <div class="field-help">
        Use Visual like WordPress/Shopify, or switch to HTML to edit the source directly.
    </div>
</div>
