<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Czytnik i Edytor MD</title>
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Marked Markdown Parser -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <style>
        :root {
            --bg-dark: #1e1e1e;
            --panel-bg: #252526;
            --accent: #b87a00;
            --accent-hover: #d18f0a;
            --accent-dim: rgba(184, 122, 0, 0.1);
            --text-main: #cccccc;
            --text-muted: #858585;
            --text-bright: #ffffff;
            --border: #3d3d3d;
            --input-bg: #2d2d2d;
            --editor-bg: #1e1e1e;
            --preview-bg: #252526;
            --green: #4ec994;
            --green-hover: #6ae0ab;
            --orange: #ce9178;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            scrollbar-width: thin;
            scrollbar-color: #454545 transparent;
        }

        /* Scrollbars */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: #454545;
            border-radius: 3px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #6a6a6a;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-main);
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            font-size: 13px;
        }

        /* Top Bar */
        .toolbar {
            height: 56px;
            background-color: var(--panel-bg);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            flex-shrink: 0;
            z-index: 10;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo-section i {
            color: var(--accent);
            font-size: 20px;
        }

        .logo-section h1 {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-bright);
            letter-spacing: 0.5px;
        }

        .file-info {
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            color: var(--text-muted);
            background: rgba(0, 0, 0, 0.2);
            padding: 4px 8px;
            border-radius: 4px;
            border: 1px solid var(--border);
            max-width: 250px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .actions-section {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* Buttons styling */
        .btn {
            background: var(--input-bg);
            border: 1px solid var(--border);
            color: var(--text-main);
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            font-size: 12px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.15s ease;
        }

        .btn:hover {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-bright);
            border-color: #555;
        }

        .btn-primary {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
        }

        .btn-primary:hover {
            background: var(--accent-hover);
            border-color: var(--accent-hover);
            box-shadow: 0 0 10px rgba(184, 122, 0, 0.3);
        }

        .btn-success {
            background: #238636;
            color: white;
            border-color: #238636;
        }

        .btn-success:hover {
            background: #2ea043;
            border-color: #2ea043;
            box-shadow: 0 0 10px rgba(35, 134, 54, 0.3);
        }

        /* View Mode Selector */
        .view-toggle {
            display: flex;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 2px;
        }

        .toggle-btn {
            background: none;
            border: none;
            color: var(--text-muted);
            padding: 6px 12px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 11px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s ease;
        }

        .toggle-btn:hover {
            color: var(--text-main);
        }

        .toggle-btn.active {
            background: var(--accent-dim);
            color: var(--accent);
            font-weight: 600;
        }

        /* Main Workspace Container */
        .workspace {
            flex: 1;
            display: flex;
            position: relative;
            height: calc(100vh - 56px);
        }

        /* Ekran startowy (drag & drop) */
        .dropzone {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 100;
            padding: 24px;
            overflow: auto;
            background:
                radial-gradient(ellipse at 50% 0%, rgba(184, 122, 0, 0.10), transparent 60%),
                var(--bg-dark);
            cursor: default;
        }

        .welcome-card {
            width: 100%;
            max-width: 580px;
            margin: auto;
            text-align: center;
            padding: 44px 40px 32px;
            background: var(--panel-bg);
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.35);
            animation: welcomeIn 0.35s cubic-bezier(0.2, 0.8, 0.2, 1) backwards;
        }

        @keyframes welcomeIn {
            from { opacity: 0; transform: translateY(12px) scale(0.985); }
            to { opacity: 1; transform: none; }
        }

        .welcome-badge {
            width: 68px;
            height: 68px;
            margin: 0 auto 20px;
            border-radius: 18px;
            display: grid;
            place-items: center;
            font-size: 28px;
            color: var(--accent);
            background: var(--accent-dim);
            border: 1px solid rgba(184, 122, 0, 0.25);
        }

        .welcome-card h2 {
            font-size: 22px;
            font-weight: 600;
            color: var(--text-bright);
            letter-spacing: -0.2px;
            margin-bottom: 8px;
        }

        .welcome-card .lead {
            color: var(--text-muted);
            font-size: 13px;
            line-height: 1.6;
            max-width: 420px;
            margin: 0 auto;
        }

        .drop-target {
            margin: 26px 0 22px;
            padding: 26px 20px;
            border: 1.5px dashed #4a4a4a;
            border-radius: 10px;
            color: var(--text-muted);
            font-size: 13px;
            cursor: pointer;
            transition: border-color 0.15s, background 0.15s, color 0.15s;
        }

        .drop-target i {
            display: block;
            font-size: 26px;
            margin-bottom: 10px;
            color: #6a6a6a;
            transition: color 0.15s, transform 0.2s;
        }

        .drop-target b {
            color: var(--text-main);
            font-weight: 500;
        }

        .drop-target:hover,
        .dropzone.dragover .drop-target {
            border-color: var(--accent);
            background: var(--accent-dim);
            color: var(--text-main);
        }

        .drop-target:hover i,
        .dropzone.dragover .drop-target i {
            color: var(--accent);
            transform: translateY(-3px);
        }

        .welcome-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .welcome-actions .btn {
            padding: 10px 20px;
            font-size: 13px;
            border-radius: 6px;
        }

        .welcome-foot {
            display: flex;
            justify-content: center;
            gap: 18px;
            flex-wrap: wrap;
            margin-top: 26px;
            padding-top: 18px;
            border-top: 1px solid var(--border);
            font-size: 11px;
            color: var(--text-muted);
        }

        .welcome-foot i {
            color: var(--accent);
            margin-right: 5px;
        }

        /* Split Panels */
        .panel {
            flex: 1;
            height: 100%;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .panel-header {
            height: 36px;
            background: #1c1c1c;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 16px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
        }

        /* Editor Area */
        .editor-panel {
            background: var(--editor-bg);
            border-right: 1px solid var(--border);
        }

        .editor-textarea {
            flex: 1;
            width: 100%;
            background: var(--editor-bg);
            color: #d4d4d4;
            border: none;
            padding: 20px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            line-height: 1.6;
            resize: none;
            outline: none;
        }

        /* Preview Area */
        .preview-panel {
            background: var(--preview-bg);
            overflow-y: auto;
        }

        .preview-content {
            padding: 30px;
            line-height: 1.7;
            font-size: 14px;
        }

        /* Markdown Styling inside Preview */
        .preview-content h1, 
        .preview-content h2, 
        .preview-content h3, 
        .preview-content h4, 
        .preview-content h5, 
        .preview-content h6 {
            color: var(--text-bright);
            margin-top: 24px;
            margin-bottom: 12px;
            font-weight: 600;
            line-height: 1.25;
        }

        .preview-content h1 { font-size: 2em; border-bottom: 1px solid var(--border); padding-bottom: 0.3em; }
        .preview-content h2 { font-size: 1.5em; border-bottom: 1px solid var(--border); padding-bottom: 0.3em; }
        .preview-content h3 { font-size: 1.25em; }

        .preview-content p {
            margin-top: 0;
            margin-bottom: 16px;
        }

        .preview-content a {
            color: var(--accent);
            text-decoration: none;
        }

        .preview-content a:hover {
            text-decoration: underline;
        }

        .preview-content code {
            background: rgba(255, 255, 255, 0.08);
            padding: 3px 6px;
            border-radius: 4px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 85%;
            color: var(--orange);
        }

        .preview-content pre {
            background: #1e1e1e;
            padding: 16px;
            border-radius: 6px;
            overflow-x: auto;
            margin-bottom: 16px;
            border: 1px solid var(--border);
        }

        .preview-content pre code {
            background: none;
            padding: 0;
            border-radius: 0;
            color: #d4d4d4;
            font-size: 13px;
        }

        .preview-content blockquote {
            border-left: 4px solid var(--accent);
            padding: 0 15px;
            color: var(--text-muted);
            margin-bottom: 16px;
        }

        .preview-content ul, 
        .preview-content ol {
            padding-left: 20px;
            margin-bottom: 16px;
        }

        .preview-content li {
            margin-top: 4px;
        }

        .preview-content table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 16px;
        }

        .preview-content th, 
        .preview-content td {
            border: 1px solid var(--border);
            padding: 8px 12px;
        }

        .preview-content th {
            background-color: rgba(255, 255, 255, 0.05);
            font-weight: 600;
        }

        .preview-content tr:nth-child(2n) {
            background-color: rgba(255, 255, 255, 0.02);
        }

        .preview-content img {
            max-width: 100%;
            height: auto;
            border-radius: 4px;
            border: 1px solid var(--border);
        }

        .hidden {
            display: none !important;
        }

        /* Toast notifications styling */
        #toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #2e220a;
            color: #f0b84a;
            border-left: 4px solid var(--accent);
            padding: 12px 24px;
            border-radius: 4px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.5);
            z-index: 1000;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        #toast.show {
            transform: translateY(0);
            opacity: 1;
        }
        
        #toast.success {
            background: #1a3a2a;
            color: var(--green);
            border-left-color: var(--green);
        }
        /* Modal windows */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(2px);
            z-index: 3000;
            display: none;
            justify-content: center;
            align-items: center;
            font-family: 'Inter', sans-serif;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-box {
            background: #2d2d30;
            border: 1px solid #454545;
            padding: 28px;
            border-radius: 6px;
            max-width: 420px;
            width: 90%;
            text-align: center;
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.6);
        }

        .modal-box h3 {
            font-size: 16px;
            margin-bottom: 12px;
        }

        .modal-box p {
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.5;
        }

        .modal-box button {
            font-family: 'Inter', sans-serif;
            font-size: 12px;
        }
        /* Pasek narzędzi: grupy przycisków */
        .toolbar { gap: 16px; }
        .actions-section { gap: 10px; flex-wrap: wrap; justify-content: flex-end; }
        .tb-group { display: flex; align-items: center; gap: 6px; }
        .tb-sep { width: 1px; height: 24px; background: var(--border); }
        .actions-section .btn { padding: 7px 12px; }
        .btn:disabled { opacity: 0.45; cursor: not-allowed; }
        .btn.attn { border-color: var(--accent); color: var(--accent); animation: attnPulse 1.4s ease-in-out infinite; }
        @keyframes attnPulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(184, 122, 0, 0.55); } 50% { box-shadow: 0 0 0 6px rgba(184, 122, 0, 0); } }
        @media (max-width: 1100px) { .btn .lbl { display: none; } }

        /* ruch: miękkie animacje */
        :root { --ease: cubic-bezier(0.22, 1, 0.36, 1); }
        @keyframes rdPop { from { opacity: 0; transform: translateY(8px) scale(0.95); } to { opacity: 1; transform: none; } }
        @keyframes rdFade { from { opacity: 0; } to { opacity: 1; } }
        .btn, .toggle-btn { transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease, transform 0.25s var(--ease); }
        .btn:active, .toggle-btn:active { transform: scale(0.965); }
        .modal-overlay.active { animation: rdFade 0.22s ease backwards; }
        .modal-overlay.active .modal-box { animation: rdPop 0.4s var(--ease) backwards; }
        .preview-content, .tree-container { animation: rdFade 0.4s ease backwards; }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation-duration: 0.01ms !important; transition-duration: 0.01ms !important; } }
    </style>
</head>
<body>

    <!-- Notification Toast -->
    <div id="toast"><i class="fa-solid fa-info-circle"></i> <span id="toast-msg">Komunikat</span></div>

    <!-- Hidden file input -->
    <input type="file" id="fileInput" accept=".md" class="hidden">

    <!-- Confirmation Modal (Nie-systemowe) -->
    <div class="modal-overlay" id="confirmModal">
        <div class="modal-box">
            <h3 style="color:var(--accent);" id="confirmTitle">Potwierdzenie</h3>
            <p style="margin-bottom: 20px;" id="confirmMessage">Czy na pewno chcesz to zrobić?</p>
            <div style="display:flex; justify-content:center; gap:10px;">
                <button id="btnConfirmYes" style="background:#da3633; color:white; border:none; padding:10px 20px; border-radius:4px; cursor:pointer; font-weight:600;">Tak</button>
                <button id="btnConfirmNo" style="background:#30363d; color:white; border:none; padding:10px 20px; border-radius:4px; cursor:pointer;">Anuluj</button>
            </div>
        </div>
    </div>

    <div class="toolbar">
        <div class="logo-section">
            <i class="fa-regular fa-file-code"></i>
            <h1>Czytnik i Edytor MD</h1>
            <span class="file-info" id="fileNameDisplay">Brak wczytanego pliku</span>
        </div>

        <div class="actions-section">
            <div class="tb-group">
                <button class="btn " id="btnStartEmptyTb" title="Nowy pusty dokument"><i class="fa-solid fa-file-circle-plus"></i> <span class="lbl">Nowy</span></button>
                <button class="btn btn-primary" id="btnLoad" title="Otwórz plik z dysku"><i class="fa-solid fa-folder-open"></i> <span class="lbl">Otwórz</span></button>
                <button class="btn " id="btnRefresh" title="Odśwież treść z dysku"><i class="fa-solid fa-rotate"></i> <span class="lbl">Odśwież</span></button>
                <button class="btn btn-success" id="btnSave" title="Zapisz na dysku (Ctrl+S)"><i class="fa-solid fa-download"></i> <span class="lbl">Zapisz</span></button>
            </div>
            <div class="tb-sep"></div>
            <div class="view-toggle">
                <button class="toggle-btn" id="toggleEdit" title="Tylko edycja"><i class="fa-solid fa-code"></i> Edycja</button>
                <button class="toggle-btn active" id="toggleSplit" title="Podzielony widok"><i class="fa-solid fa-columns"></i> Split</button>
                <button class="toggle-btn" id="togglePreview" title="Tylko podgląd"><i class="fa-solid fa-eye"></i> Podgląd</button>
            </div>
        </div>
    </div>

        <div class="workspace">
        <!-- Drag & Drop Zone -->
        <div class="dropzone" id="dropzone">
            <div class="welcome-card">
                <div class="welcome-badge"><i class="fa-brands fa-markdown"></i></div>
                <h2>Czytnik i Edytor Markdown</h2>
                <p class="lead">Otwórz plik <b>.md</b>, aby zobaczyć podgląd na żywo, edytuj treść i zapisz zmiany z powrotem na dysku.</p>
                <div class="drop-target" id="dropTarget">
                    <i class="fa-solid fa-arrow-up-from-bracket"></i>
                    <b>Przeciągnij plik tutaj</b> lub kliknij, aby wybrać z dysku
                </div>
                <div class="welcome-actions">
                    <button class="btn btn-primary" id="btnPick"><i class="fa-solid fa-folder-open"></i> Wybierz plik</button>
                    <button class="btn" id="btnStartEmpty"><i class="fa-solid fa-file-circle-plus"></i> Nowy dokument</button>
                </div>
                <div class="welcome-foot">
                    <span><i class="fa-solid fa-lock"></i>Plik nie opuszcza Twojego komputera</span>
                    <span><i class="fa-solid fa-eye"></i>Podgląd na żywo</span>
                    <span><i class="fa-solid fa-keyboard"></i>Ctrl+S zapisuje</span>
                </div>
            </div>
        </div>

        <!-- Editor Panel -->
        <div class="panel editor-panel" id="editorPanel">
            <div class="panel-header">
                <span>Edytor Markdown</span>
                <span id="charCount">Znaki: 0</span>
            </div>
            <textarea class="editor-textarea" id="editorTextarea" placeholder="Wpisz lub wklej treść Markdown tutaj..."></textarea>
        </div>

        <!-- Preview Panel -->
        <div class="panel preview-panel" id="previewPanel">
            <div class="panel-header">
                <span>Podgląd HTML</span>
                <span>Live Preview</span>
            </div>
            <div class="preview-content" id="previewContent"></div>
        </div>
    </div>

    <script>
        // DOM Elements
        const fileInput = document.getElementById('fileInput');
        const btnLoad = document.getElementById('btnLoad');
        const btnSave = document.getElementById('btnSave');
        const toggleEdit = document.getElementById('toggleEdit');
        const toggleSplit = document.getElementById('toggleSplit');
        const togglePreview = document.getElementById('togglePreview');
        const dropzone = document.getElementById('dropzone');
        const editorPanel = document.getElementById('editorPanel');
        const previewPanel = document.getElementById('previewPanel');
        const editorTextarea = document.getElementById('editorTextarea');
        const previewContent = document.getElementById('previewContent');
        const fileNameDisplay = document.getElementById('fileNameDisplay');
        const charCount = document.getElementById('charCount');
        const toast = document.getElementById('toast');
        const toastMsg = document.getElementById('toast-msg');

        const PICKER_TYPES = { description: 'Markdown', accept: { 'text/markdown': ['.md', '.markdown'], 'text/plain': ['.txt'] } };
        const applyText = (t) => { editorTextarea.value = t; renderMarkdown(); };
        function showConfirm(message, title, onYes) {
            const modal = document.getElementById('confirmModal');
            document.getElementById('confirmTitle').textContent = title || 'Potwierdzenie';
            document.getElementById('confirmMessage').innerHTML = message;
            modal.classList.add('active');
            document.getElementById('btnConfirmYes').onclick = () => { modal.classList.remove('active'); onYes(); };
            document.getElementById('btnConfirmNo').onclick = () => modal.classList.remove('active');
        }
        let loadedFileName = 'dokument.md';

        // Default Help Content
        const defaultContent = `# Witamy w Czytniku i Edytorze Markdown! 📝

To narzędzie pozwala na wczytywanie, przeglądanie, edycję oraz zapisywanie plików \`.md\` bezpośrednio na Twoim komputerze.

## Kluczowe Funkcje:
1. **Wczytywanie z dysku**: Kliknij przycisk **Wczytaj z dysku** w prawym górnym rogu lub przeciągnij i upuść plik na okno programu.
2. **Edycja na żywo**: Po lewej stronie znajduje się edytor, a po prawej natychmiastowy podgląd.
3. **Tryby widoku**: Możesz przełączać widok między samą *Edycją*, *Split* (podziałem), a samym *Podglądem*.
4. **Zapis na dysku**: Kliknij **Zapisz na dysku**, aby pobrać zmodyfikowany plik.

---

### Przykład formatowania Markdown:
Możesz tworzyć **pogrubienia**, *pochylenia*, a także bloki kodu:

\`\`\`javascript
// Live preview kodu
function helloWorld() {
    console.log("Witaj Świecie!");
}
helloWorld();
\`\`\`

> A oto cytat wyróżniony specjalnym kolorem akcentu.

Życzymy przyjemnej pracy z dokumentacją!
`;

        // Render Markdown
        function renderMarkdown() {
            const text = editorTextarea.value;
            previewContent.innerHTML = marked.parse(text);
            charCount.textContent = `Znaki: ${text.length}`;
        }

        // Show Toast Notification
        function showToast(message, type = 'info') {
            toastMsg.textContent = message;
            toast.className = ''; // reset classes
            if (type === 'success') {
                toast.classList.add('success');
                toast.innerHTML = `<i class="fa-solid fa-circle-check"></i> <span id="toast-msg">${message}</span>`;
            } else {
                toast.innerHTML = `<i class="fa-solid fa-info-circle"></i> <span id="toast-msg">${message}</span>`;
            }
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        // --- Otwieranie i odświeżanie pliku z dysku ---
        // File System Access API (Chrome/Edge) trzyma uchwyt do pliku, więc odświeżenie czyta
        // aktualną wersję z dysku bez ponownego wybierania. Inne przeglądarki: wybór pliku od nowa.
        const HAS_FSA = 'showOpenFilePicker' in window;
        let fileHandle = null, lastFile = null, lastText = '', lastMod = 0;
        const btnRefresh = document.getElementById('btnRefresh');

        function markSynced(file, text) {
            lastFile = file;
            lastText = text;
            lastMod = file.lastModified;
            btnRefresh.classList.remove('attn');
            btnRefresh.title = 'Odśwież treść z dysku';
            btnRefresh.disabled = false;
        }

        async function loadFile(file, handle = null) {
            if (!file) return;
            try {
                const text = await file.text();
                fileHandle = handle;
                applyText(text);
                loadedFileName = file.name;
                fileNameDisplay.textContent = file.name;
                dropzone.classList.add('hidden');
                markSynced(file, text);
                showToast(`Wczytano plik: ${file.name}`, 'success');
            } catch (err) {
                showToast('Wystąpił błąd podczas wczytywania pliku.', 'error');
            }
        }

        async function openFilePicker() {
            if (!HAS_FSA) return fileInput.click();
            try {
                const [h] = await window.showOpenFilePicker({ types: [PICKER_TYPES], multiple: false });
                await loadFile(await h.getFile(), h);
            } catch (err) {
                if (err.name !== 'AbortError') fileInput.click();
            }
        }

        async function refreshFile() {
            if (!lastFile) {
                showToast('Najpierw otwórz plik z dysku.', 'info');
                return;
            }
            let file, text;
            try {
                file = fileHandle ? await fileHandle.getFile() : lastFile;
                text = await file.text();
            } catch (err) {
                showToast('Nie można odczytać pliku – wybierz go ponownie.', 'error');
                openFilePicker();
                return;
            }
            if (text === editorTextarea.value) {
                markSynced(file, text);
                showToast('Plik jest aktualny.', 'info');
                return;
            }
            const apply = () => {
                applyText(text);
                markSynced(file, text);
                showToast('Odświeżono treść z dysku.', 'success');
            };
            if (editorTextarea.value !== lastText) {
                showConfirm('W edytorze są niezapisane zmiany.<br>Odświeżenie zastąpi je treścią z dysku.', 'Odśwież z dysku', apply);
            } else {
                apply();
            }
        }

        // Wykrywanie zmiany pliku na dysku (np. przez inną aplikację) – podświetla przycisk Odśwież
        async function checkDiskChange() {
            if (!fileHandle || document.hidden) return;
            try {
                const f = await fileHandle.getFile();
                if (f.lastModified !== lastMod && !btnRefresh.classList.contains('attn')) {
                    btnRefresh.classList.add('attn');
                    btnRefresh.title = 'Plik zmienił się na dysku – kliknij, aby odświeżyć';
                    showToast('Plik zmieniony na dysku – kliknij Odśwież.', 'info');
                }
            } catch (err) { /* plik chwilowo niedostępny */ }
        }
        setInterval(checkDiskChange, 2000);
        window.addEventListener('focus', checkDiskChange);
        btnRefresh.addEventListener('click', refreshFile);
        btnRefresh.disabled = true;

        // Trigger file input
        btnLoad.addEventListener('click', openFilePicker);
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                loadFile(e.target.files[0]);
                e.target.value = '';
            }
        });

        // Save file to client PC
        btnSave.addEventListener('click', () => {
            const text = editorTextarea.value;
            if (!text.trim()) {
                showToast('Brak zawartości do zapisania.', 'info');
                return;
            }

            const blob = new Blob([text], { type: 'text/markdown;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = loadedFileName;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            showToast('Zapisano plik na dysku!', 'success');
        });

        // View Mode toggling
        toggleEdit.addEventListener('click', () => {
            setActiveToggle(toggleEdit);
            editorPanel.classList.remove('hidden');
            previewPanel.classList.add('hidden');
        });

        toggleSplit.addEventListener('click', () => {
            setActiveToggle(toggleSplit);
            editorPanel.classList.remove('hidden');
            previewPanel.classList.remove('hidden');
        });

        togglePreview.addEventListener('click', () => {
            setActiveToggle(togglePreview);
            editorPanel.classList.add('hidden');
            previewPanel.classList.remove('hidden');
        });

        function setActiveToggle(activeBtn) {
            [toggleEdit, toggleSplit, togglePreview].forEach(btn => btn.classList.remove('active'));
            activeBtn.classList.add('active');
        }

        // Textarea events
        editorTextarea.addEventListener('input', renderMarkdown);

        // Drag & Drop
        document.getElementById('dropTarget').addEventListener('click', openFilePicker);
        document.getElementById('btnPick').addEventListener('click', openFilePicker);

        // Podczas przeciągania pliku ekran startowy pojawia się na wierzchu;
        // po zakończeniu przeciągania wraca do poprzedniego stanu (nie zostaje na edytorze).
        let hiddenBeforeDrag = false;
        const hasFiles = (e) => e.dataTransfer && Array.from(e.dataTransfer.types || []).includes('Files');

        function endDrag() {
            dropzone.classList.remove('dragover');
            if (hiddenBeforeDrag) {
                dropzone.classList.add('hidden');
                hiddenBeforeDrag = false;
            }
        }

        window.addEventListener('dragover', (e) => {
            if (!hasFiles(e)) return;
            e.preventDefault();
            if (dropzone.classList.contains('hidden')) {
                hiddenBeforeDrag = true;
                dropzone.classList.remove('hidden');
            }
            dropzone.classList.add('dragover');
        });

        window.addEventListener('dragleave', (e) => {
            if (!e.relatedTarget) endDrag();
        });

        window.addEventListener('drop', (e) => {
            e.preventDefault();
            const file = e.dataTransfer.files[0];
            const hp = e.dataTransfer.items && e.dataTransfer.items[0] && e.dataTransfer.items[0].getAsFileSystemHandle ? e.dataTransfer.items[0].getAsFileSystemHandle() : null;
            endDrag();
            if (!file) return;
            if (!/\.(md|markdown|txt)$/i.test(file.name)) {
                showToast('Obsługiwane są pliki .md, .markdown i .txt', 'error');
                return;
            }
            Promise.resolve(hp).then(h => loadFile(file, h && h.kind === 'file' ? h : null), () => loadFile(file));
        });

        // Ctrl+S zapisuje plik
        window.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
                e.preventDefault();
                btnSave.click();
            }
        });

        // Initialize with default help text but show dropzone
        editorTextarea.value = defaultContent;
        renderMarkdown();

        // Nowy, pusty dokument
        const startEmpty = () => {
            dropzone.classList.add('hidden');
            editorTextarea.value = '';
            loadedFileName = 'dokument.md';
            fileNameDisplay.textContent = 'dokument.md';
            fileHandle = null; lastFile = null; lastText = '';
            btnRefresh.disabled = true;
            renderMarkdown();
            editorTextarea.focus();
        };
        document.getElementById('btnStartEmpty').addEventListener('click', startEmpty);
        document.getElementById('btnStartEmptyTb').addEventListener('click', startEmpty);
    </script>
</body>
</html>
