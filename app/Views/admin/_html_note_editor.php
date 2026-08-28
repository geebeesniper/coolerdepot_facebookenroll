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

            <button type="button" class="wp-note-tool" data-cmd="bold" title="Bold" aria-label="Bold"><b>B</b></button>
            <button type="button" class="wp-note-tool" data-cmd="italic" title="Italic" aria-label="Italic"><i>I</i></button>
            <button type="button" class="wp-note-tool" data-cmd="underline" title="Underline" aria-label="Underline"><u>U</u></button>
            <button type="button" class="wp-note-tool" data-cmd="strikeThrough" title="Strikethrough" aria-label="Strikethrough"><s>S</s></button>

            <span class="wp-note-toolbar-separator" aria-hidden="true"></span>

            <button type="button" class="wp-note-tool wp-note-tool-wide" data-cmd="insertUnorderedList" title="Bulleted list" aria-label="Bulleted list">• List</button>
            <button type="button" class="wp-note-tool wp-note-tool-wide" data-cmd="insertOrderedList" title="Numbered list" aria-label="Numbered list">1. List</button>

            <button type="button" class="wp-note-tool" data-cmd="formatBlock" data-value="blockquote" title="Blockquote" aria-label="Blockquote">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M7.3 6.8H3.8v5.1h3.1c-.1 2.2-1.1 3.5-3.1 4v2.2c4.2-.6 6.2-3.4 6.2-7.4V6.8H7.3Zm10.2 0H14v5.1h3.1c-.1 2.2-1.1 3.5-3.1 4v2.2c4.2-.6 6.2-3.4 6.2-7.4V6.8h-2.7Z"/>
                </svg>
            </button>

            <span class="wp-note-toolbar-separator" aria-hidden="true"></span>

            <button type="button" class="wp-note-tool" data-note-link title="Insert/edit link" aria-label="Insert/edit link">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M10.6 13.4a2 2 0 0 0 2.8 0l3.2-3.2a2 2 0 0 0-2.8-2.8l-1.4 1.4-1.4-1.4 1.4-1.4a4 4 0 1 1 5.6 5.6l-3.2 3.2a4 4 0 0 1-5.6 0l-.7-.7 1.4-1.4.7.7Zm2.8-2.8a2 2 0 0 0-2.8 0l-3.2 3.2a2 2 0 0 0 2.8 2.8l1.4-1.4 1.4 1.4-1.4 1.4A4 4 0 1 1 6 12.4l3.2-3.2a4 4 0 0 1 5.6 0l.7.7-1.4 1.4-.7-.7Z"/>
                </svg>
            </button>

            <button type="button" class="wp-note-tool" data-cmd="unlink" title="Remove link" aria-label="Remove link">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="m4.3 3 16.7 16.7-1.3 1.3-3.4-3.4a4 4 0 0 1-4.9-.6l-.7-.7 1.4-1.4.7.7c.5.5 1.2.7 1.9.5l-2-2-2.5 2.5a2 2 0 0 1-2.8-2.8l2.5-2.5-1.4-1.4L6 12.4A4 4 0 0 0 11.6 18l.7.7a6 6 0 0 0 5.4 1.6L3 4.3 4.3 3Zm8.1 5.8 1.4-1.4a2 2 0 0 1 2.8 2.8l-1.4 1.4 1.4 1.4 1.4-1.4A4 4 0 1 0 12.4 6l-1.4 1.4 1.4 1.4Z"/>
                </svg>
            </button>

            <button type="button" class="wp-note-tool" data-cmd="removeFormat" title="Clear formatting" aria-label="Clear formatting">Tx</button>

            <span class="wp-note-toolbar-separator" aria-hidden="true"></span>

            <button type="button" class="wp-note-tool" data-cmd="undo" title="Undo" aria-label="Undo">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 7V3L3 9l6 6v-4c4.4 0 7.5 1.4 9.8 4.5-.9-4.4-3.5-8.5-9.8-8.5Z"/></svg>
            </button>
            <button type="button" class="wp-note-tool" data-cmd="redo" title="Redo" aria-label="Redo">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 7V3l6 6-6 6v-4c-4.4 0-7.5 1.4-9.8 4.5C6.1 11.1 8.7 7 15 7Z"/></svg>
            </button>
        </div>

        <div class="wp-note-linkbar hidden" data-note-linkbar aria-hidden="true">
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
            <button type="button" class="tiny" data-note-link-apply>Apply</button>
            <button type="button" class="tiny" data-note-link-cancel>Cancel</button>
        </div>

        <div
            class="html-editor wp-note-visual"
            contenteditable="true"
            data-html-editor
            role="textbox"
            aria-multiline="true"
            aria-label="Visual HTML note editor"
        ><?= $noteValue ?></div>

        <div class="wp-code-editor hidden" data-code-editor>
            <div
                class="wp-code-gutter"
                data-code-gutter
                aria-hidden="true"
            >1</div>

            <div class="wp-code-stage">
                <pre
                    class="wp-code-highlight"
                    data-code-highlight
                    aria-hidden="true"
                ></pre>

                <textarea
                    id="<?= Util::e($fieldId) ?>-source"
                    class="html-source wp-note-source"
                    name="<?= Util::e($fieldName) ?>"
                    rows="12"
                    spellcheck="false"
                    autocapitalize="off"
                    autocomplete="off"
                    autocorrect="off"
                    data-html-source
                    aria-label="HTML source editor"
                ><?= Util::e($noteValue) ?></textarea>
            </div>
        </div>

        <div class="wp-note-statusbar">
            <span data-note-status>Visual editor</span>
            <span data-note-cursor>HTML supported</span>
        </div>
    </div>

    <div class="field-help">
        Visual mode for rich text. HTML mode includes line numbers, syntax highlighting, Tab indentation, and source editing.
    </div>
</div>
