import { onMounted, ref, type Ref } from 'vue';
import '../../css/email-editor.css';

/**
 * Shared Quill 2 (CDN) setup for the email composers, e.g. Notify Participants and
 * Email Certificates: toolbars, Gmail-style tooltips, the default signature and the
 * conversion to email-ready HTML. The editor styles live in resources/css/email-editor.css
 * (use the `email-quill` class on the editor wrapper, `email-quill-signature` for a signature,
 * and `email-preview-body` / `email-preview-signature` for previews).
 */

/** A Quill instance. Quill is loaded from the CDN at runtime, so it has no local types. */
 
export type QuillEditor = any;

export interface EmailEditorSpec {
    container: Ref<HTMLElement | null>;
    toolbar: unknown[];
    placeholder: string;
    html: string;
}

const QUILL_SCRIPT = 'https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js';
const QUILL_STYLESHEET = 'https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css';

export const BODY_TOOLBAR = [
    [{ align: [] }],
    [{ color: [] }, { background: [] }],
    ['bold', 'italic', 'underline', 'strike'],
    [{ header: [1, 2, 3, false] }],
    ['blockquote', 'code-block'],
    [{ indent: '-1' }, { indent: '+1' }],
    [{ list: 'check' }, { list: 'bullet' }, { list: 'ordered' }],
    ['link'],
    ['clean'],
];

export const SIGNATURE_TOOLBAR = [['bold', 'italic', 'underline'], ['link']];

const TOOLBAR_TITLES: Record<string, string> = {
    'ql-align': 'Text alignment',
    'ql-color': 'Text color',
    'ql-background': 'Highlight color',
    'ql-bold': 'Bold (Ctrl+B)',
    'ql-italic': 'Italic (Ctrl+I)',
    'ql-underline': 'Underline (Ctrl+U)',
    'ql-strike': 'Strikethrough',
    'ql-header': 'Heading',
    'ql-blockquote': 'Quote',
    'ql-code-block': 'Code block',
    'ql-link': 'Insert link (Ctrl+K)',
    'ql-clean': 'Clear formatting',
};

const INDENT_TITLES: Record<string, string> = { '-1': 'Decrease indent', '+1': 'Increase indent' };
const LIST_TITLES: Record<string, string> = { check: 'Checklist', bullet: 'Bulleted list', ordered: 'Numbered list' };

const quillGlobal = (): QuillEditor => (window as unknown as { Quill?: QuillEditor }).Quill;

const loadScript = (src: string): Promise<void> =>
    new Promise((resolve, reject) => {
        if (document.querySelector(`script[src="${src}"]`)) {
            resolve();
            return;
        }
        const script = document.createElement('script');
        script.src = src;
        script.onload = () => resolve();
        script.onerror = () => reject();
        document.head.appendChild(script);
    });

const loadLink = (href: string, id: string) => {
    if (document.getElementById(id)) return;
    const link = document.createElement('link');
    link.id = id;
    link.rel = 'stylesheet';
    link.href = href;
    document.head.appendChild(link);
};

const waitFor = async (check: () => boolean, attempts = 30) => {
    for (let attempt = 0; attempt < attempts && !check(); attempt++) {
        await new Promise<void>((resolve) => setTimeout(resolve, 100));
    }
    return check();
};

const parseDate = (value?: string | null): Date | null => {
    if (!value) return null;
    const date = new Date(`${value.slice(0, 10)}T00:00:00`);
    return Number.isNaN(date.getTime()) ? null : date;
};

/**
 * Batch dates worded like the emails (Batch::scheduleLabel()), e.g. "March 3–5, 2026"
 * or "March 30 – April 2, 2026". Empty when the batch has no start date.
 */
export const formatEmailSchedule = (dateStart?: string | null, dateEnd?: string | null): string => {
    const start = parseDate(dateStart);
    if (!start) return '';
    const end = parseDate(dateEnd) ?? start;
    const month = (date: Date) => date.toLocaleDateString('en-US', { month: 'long' });
    const full = (date: Date) => `${month(date)} ${date.getDate()}, ${date.getFullYear()}`;

    if (start.getTime() === end.getTime()) return full(start);
    if (start.getFullYear() === end.getFullYear() && start.getMonth() === end.getMonth()) {
        return `${month(start)} ${start.getDate()}–${end.getDate()}, ${end.getFullYear()}`;
    }
    if (start.getFullYear() === end.getFullYear()) {
        return `${month(start)} ${start.getDate()} – ${month(end)} ${end.getDate()}, ${end.getFullYear()}`;
    }
    return `${full(start)} – ${full(end)}`;
};

export const escapeHtml = (value: string) =>
    value.replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]!);

/** The sender's name and office, as both email composers pre-fill it. */
export const buildDefaultSignature = (senderName: string) =>
    [
        'Regards,',
        '',
        senderName.trim() ? `<strong>${escapeHtml(senderName.trim())}</strong>` : null,
        'TESDA Development Institute',
        'Technical Education and Skills Development Authority (TESDA)',
        'Office of the Deputy Director General for Administration and Innovation',
    ]
        .filter((line): line is string => line !== null)
        .map((line) => `<p>${line || '<br>'}</p>`)
        .join('');

// Quill has no hover labels; add Gmail-style tooltips to every toolbar control.
const addToolbarTitles = (editor: QuillEditor) => {
    const toolbar: HTMLElement | undefined = editor.getModule('toolbar')?.container;
    toolbar?.querySelectorAll<HTMLElement>('button, .ql-picker').forEach((control) => {
        const format = control.classList[0] ?? '';
        const value = control.getAttribute('value') ?? '';
        const title = format === 'ql-indent' ? INDENT_TITLES[value] : format === 'ql-list' ? LIST_TITLES[value] : TOOLBAR_TITLES[format];
        if (title) {
            control.setAttribute('title', title);
            control.setAttribute('aria-label', title);
        }
    });
};

const createEditor = (container: HTMLElement, toolbar: unknown[], placeholder: string, html: string): QuillEditor => {
    const Quill = quillGlobal();
    // Quill puts its toolbar next to the container; clear any left over from a previous open.
    container.parentElement?.querySelectorAll(':scope > .ql-toolbar').forEach((element) => element.remove());
    container.innerHTML = '';

    const editor = new Quill(container, { theme: 'snow', placeholder, bounds: container, modules: { toolbar } });
    editor.clipboard.dangerouslyPasteHTML(html, 'silent');
    addToolbarTitles(editor);
    return editor;
};

/** Replace an editor's content without it counting as a user edit. */
export const setEditorHtml = (editor: QuillEditor, html: string) => {
    editor?.clipboard.dangerouslyPasteHTML(html, 'silent');
};

export const isEditorEmpty = (editor: QuillEditor) => !editor || editor.getText().trim() === '';

const CHECKLIST_MARKERS: Record<string, string> = { checked: '☑', unchecked: '☐' };

/**
 * Turn the editor content into email-ready HTML: semantic tags, Quill's alignment/indent
 * classes as inline styles (email clients ignore stylesheets) and checklists as ☑/☐ items.
 * Used for both the preview and the message that is sent, so they always match.
 */
export const toEmailHtml = (editor: QuillEditor): string => {
    if (isEditorEmpty(editor)) return '';

    const semanticHtml: string = editor
        .getSemanticHTML()
        // Quill writes every space as &nbsp;, which keeps long lines from wrapping in inboxes.
        .replace(/(?:&nbsp;)+/g, (run: string) => ' ' + '&nbsp;'.repeat(run.length / 6 - 1))
        .replace(/<p><\/p>/g, '<p><br></p>');

    const documentFragment = new DOMParser().parseFromString(`<body>${semanticHtml}</body>`, 'text/html');
    const root = documentFragment.body;

    root.querySelectorAll<HTMLElement>('[class]').forEach((element) => {
        element.classList.forEach((className) => {
            const alignment = className.match(/^ql-align-(center|right|justify)$/);
            const indent = className.match(/^ql-indent-(\d)$/);
            if (alignment) element.style.textAlign = alignment[1];
            if (indent) element.style.paddingLeft = `${Number(indent[1]) * 3}em`;
        });
        element.removeAttribute('class');
    });

    root.querySelectorAll<HTMLElement>('li[data-list]').forEach((item) => {
        const marker = CHECKLIST_MARKERS[item.dataset.list ?? ''];
        if (marker) {
            item.prepend(`${marker} `);
            item.parentElement?.style.setProperty('list-style-type', 'none');
            item.parentElement?.style.setProperty('padding-left', '4px');
        }
        item.removeAttribute('data-list');
    });

    root.querySelectorAll('[data-language]').forEach((element) => element.removeAttribute('data-language'));

    return root.innerHTML;
};

/**
 * Loads Quill from the CDN when the page mounts (so the editor is ready the moment a
 * modal opens) and creates the editors of one modal on demand.
 *
 * @param isOpen Whether the modal that owns the editors is open right now.
 */
export function useEmailEditors(isOpen: () => boolean) {
    const editorsReady = ref(false);
    const editorLoadFailed = ref(false);

    // Bumped on every mount/unmount so a slow init from an earlier open never runs on top of a newer one.
    let session = 0;

    /** Create one editor per spec, in order. Resolves to null when the modal closed or Quill couldn't load. */
    const mountEditors = async (specs: EmailEditorSpec[]): Promise<QuillEditor[] | null> => {
        const current = ++session;
        const hasQuill = await waitFor(() => !!quillGlobal());
        const hasContainers = await waitFor(() => specs.every((spec) => !!spec.container.value));

        if (current !== session) return null;

        if (!hasQuill || !hasContainers || !isOpen()) {
            editorLoadFailed.value = isOpen();
            return null;
        }

        const editors = specs.map((spec) => createEditor(spec.container.value!, spec.toolbar, spec.placeholder, spec.html));
        editorsReady.value = true;
        return editors;
    };

    const unmountEditors = () => {
        session++;
        editorsReady.value = false;
        editorLoadFailed.value = false;
    };

    onMounted(async () => {
        loadLink(QUILL_STYLESHEET, 'quill-css');
        if (!quillGlobal()) {
            await loadScript(QUILL_SCRIPT).catch(() => {
                editorLoadFailed.value = isOpen();
            });
        }
    });

    return { editorsReady, editorLoadFailed, mountEditors, unmountEditors };
}
