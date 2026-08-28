<?php
use App\Core\Util;

$fieldName = $fieldName ?? 'note';
$fieldId = $fieldId ?? 'note-' . bin2hex(random_bytes(3));
$noteValue = (string)($noteValue ?? '');
$fieldLabel = (string)($fieldLabel ?? 'Note');
$enableImageUpload = (bool)($enableImageUpload ?? false);
?>
<div class="prose-editor" data-html-note>
    <div class="prose-editor-label">
        <label for="<?= Util::e($fieldId) ?>-source"><?= Util::e($fieldLabel) ?></label>
    </div>

    <div class="prose-editor-shell">
        <div class="prose-toolbar">
            <div class="prose-tools" data-html-toolbar>
                <select
                    class="prose-format"
                    data-note-format
                    aria-label="Text style"
                >
                    <option value="p">Paragraph</option>
                    <option value="h3">Heading</option>
                    <option value="h4">Subheading</option>
                    <option value="blockquote">Quote</option>
                </select>

                <span class="prose-divider"></span>

                <button type="button" class="prose-tool" data-cmd="bold" title="Bold" aria-label="Bold"><b>B</b></button>
                <button type="button" class="prose-tool" data-cmd="italic" title="Italic" aria-label="Italic"><i>I</i></button>
                <button type="button" class="prose-tool" data-cmd="underline" title="Underline" aria-label="Underline"><u>U</u></button>

                <span class="prose-divider"></span>

                <button type="button" class="prose-tool" data-cmd="insertUnorderedList" title="Bulleted list" aria-label="Bulleted list">
                    <svg viewBox="0 0 24 24"><path d="M7 6h14v2H7V6ZM7 11h14v2H7v-2ZM7 16h14v2H7v-2ZM3 6h2v2H3V6Zm0 5h2v2H3v-2Zm0 5h2v2H3v-2Z"/></svg>
                </button>
                <button type="button" class="prose-tool" data-cmd="insertOrderedList" title="Numbered list" aria-label="Numbered list">
                    <svg viewBox="0 0 24 24"><path d="M8 6h13v2H8V6Zm0 5h13v2H8v-2Zm0 5h13v2H8v-2ZM3 5h3v4H4V7H3V5Zm0 6h3v4H3v-2h1v-1H3v-1Zm0 5h3v4H3v-2h1v-1H3v-1Z"/></svg>
                </button>

                <span class="prose-divider"></span>

                <button type="button" class="prose-tool" data-note-link title="Insert link" aria-label="Insert link">
                    <svg viewBox="0 0 24 24"><path d="M10.6 13.4a2 2 0 0 0 2.8 0l3.2-3.2a2 2 0 0 0-2.8-2.8l-1.4 1.4-1.4-1.4 1.4-1.4a4 4 0 1 1 5.6 5.6l-3.2 3.2a4 4 0 0 1-5.6 0l-.7-.7 1.4-1.4.7.7Zm2.8-2.8a2 2 0 0 0-2.8 0l-3.2 3.2a2 2 0 0 0 2.8 2.8l1.4-1.4 1.4 1.4-1.4 1.4A4 4 0 1 1 6 12.4l3.2-3.2a4 4 0 0 1 5.6 0l.7.7-1.4 1.4-.7-.7Z"/></svg>
                </button>
                <button type="button" class="prose-tool prose-image-tool" data-note-image title="Insert image" aria-label="Insert image">
                    <svg viewBox="0 0 24 24"><path d="M4 4h16v16H4V4Zm2 2v9.2l3.4-3.4 2.5 2.5 2.1-2.1 4 4V6H6Zm2.7 3.2a1.7 1.7 0 1 0 0-3.4 1.7 1.7 0 0 0 0 3.4Z"/></svg>
                </button>
                <button type="button" class="prose-tool" data-cmd="removeFormat" title="Clear formatting" aria-label="Clear formatting">Tx</button>

                <span class="prose-divider"></span>

                <button type="button" class="prose-tool" data-cmd="undo" title="Undo" aria-label="Undo">
                    <svg viewBox="0 0 24 24"><path d="M9 7V3L3 9l6 6v-4c4.4 0 7.5 1.4 9.8 4.5-.9-4.4-3.5-8.5-9.8-8.5Z"/></svg>
                </button>
                <button type="button" class="prose-tool" data-cmd="redo" title="Redo" aria-label="Redo">
                    <svg viewBox="0 0 24 24"><path d="M15 7V3l6 6-6 6v-4c-4.4 0-7.5 1.4-9.8 4.5C6.1 11.1 8.7 7 15 7Z"/></svg>
                </button>
            </div>

            <div class="prose-mode-switch" role="tablist" aria-label="Editor mode">
                <button
                    type="button"
                    class="prose-mode active"
                    data-note-mode="visual"
                    role="tab"
                    aria-selected="true"
                >
                    Visual
                </button>
                <button
                    type="button"
                    class="prose-mode"
                    data-note-mode="html"
                    role="tab"
                    aria-selected="false"
                >
                    HTML
                </button>
            </div>
        </div>

        <div class="prose-popover hidden" data-note-linkbar>
            <input
                type="url"
                placeholder="https://example.com"
                data-note-link-input
            >
            <label class="prose-check">
                <input type="checkbox" data-note-link-newtab>
                New tab
            </label>
            <button type="button" class="tiny" data-note-link-apply>Insert</button>
            <button type="button" class="tiny" data-note-link-cancel>Cancel</button>
        </div>

        <div class="prose-image-popover hidden" data-note-image-panel>
            <input
                type="url"
                class="prose-image-url"
                placeholder="Paste image URL"
                data-note-image-url
            >

            <button
                type="button"
                class="prose-popover-button"
                data-note-image-url-insert
            >
                Insert URL
            </button>

            <button
                type="button"
                class="prose-popover-button hidden"
                data-note-listing-photo
            >
                Listing photo
            </button>

            <?php if ($enableImageUpload): ?>
                <label class="prose-popover-button prose-upload-button">
                    Upload
                    <input
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        data-note-image-file
                        hidden
                    >
                </label>
            <?php endif; ?>

            <button
                type="button"
                class="prose-popover-button"
                data-note-image-cancel
            >
                Cancel
            </button>

            <div
                class="prose-image-message"
                data-note-image-message
                aria-live="polite"
            ></div>
        </div>

        <div
            class="prose-visual"
            contenteditable="true"
            data-html-editor
            role="textbox"
            aria-multiline="true"
            data-placeholder="Write a review note…"
        ><?= $noteValue ?></div>

        <div class="prose-code hidden" data-code-editor>
            <div class="prose-code-gutter" data-code-gutter>1</div>

            <div class="prose-code-stage">
                <pre
                    class="prose-code-highlight"
                    data-code-highlight
                    aria-hidden="true"
                ></pre>

                <textarea
                    id="<?= Util::e($fieldId) ?>-source"
                    class="prose-code-source"
                    name="<?= Util::e($fieldName) ?>"
                    spellcheck="false"
                    autocapitalize="off"
                    autocomplete="off"
                    autocorrect="off"
                    data-html-source
                ><?= Util::e($noteValue) ?></textarea>
            </div>
        </div>

        <div class="prose-status">
            <span data-note-status>Visual</span>
            <span data-note-cursor></span>
        </div>
    </div>
</div>
