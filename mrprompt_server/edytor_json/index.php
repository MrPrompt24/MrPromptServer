<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Czytnik i Edytor JSON</title>
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            --red: #f44747;
            --purple: #c586c0;
            --blue: #4fc1ff;
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

        /* Top Bar / Toolbar */
        .toolbar {
            height: 48px;
            background-color: var(--panel-bg);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 16px;
            flex-shrink: 0;
            z-index: 10;
            box-shadow: 0 1px 5px rgba(0, 0, 0, 0.2);
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .logo-section i {
            color: var(--accent);
            font-size: 15px;
        }

        .logo-section h1 {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-bright);
            letter-spacing: 0.3px;
        }

        .file-info {
            font-family: 'JetBrains Mono', monospace;
            font-size: 10.5px;
            color: var(--text-muted);
            background: rgba(0, 0, 0, 0.2);
            padding: 3px 6px;
            border-radius: 3px;
            border: 1px solid var(--border);
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .actions-section {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Buttons styling */
        .btn {
            background: var(--input-bg);
            border: 1px solid var(--border);
            color: var(--text-main);
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            font-size: 11px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.15s ease;
            height: 28px;
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

        /* Main Workspace Container */
        .workspace {
            flex: 1;
            display: flex;
            position: relative;
            height: calc(100vh - 48px);
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
            width: 50%;
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
            flex-shrink: 0;
        }

        /* Editor Area */
        .editor-panel {
            background: var(--editor-bg);
            border-right: 1px solid var(--border);
        }

        .editor-wrapper {
            flex: 1;
            display: flex;
            position: relative;
            overflow: hidden;
        }

        .line-numbers {
            width: 48px;
            padding: 20px 0;
            text-align: right;
            padding-right: 12px;
            color: #5a5a5a;
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            line-height: 1.6;
            background: #1a1a1a;
            border-right: 1px solid var(--border);
            user-select: none;
            overflow: hidden;
        }

        .line-number-item {
            height: 20.8px; /* line-height 1.6 of 13px */
            transition: all 0.1s ease;
        }

        .line-number-item.line-error {
            background: rgba(244, 71, 71, 0.25);
            color: #ff6b6b;
            border-left: 2.5px solid var(--red);
            padding-right: 9.5px;
        }

        .editor-textarea {
            flex: 1;
            background: transparent;
            color: #d4d4d4;
            border: none;
            padding: 20px 12px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            line-height: 1.6;
            resize: none;
            outline: none;
            overflow: auto;
            white-space: pre;
            word-wrap: normal;
        }

        /* Validation status bar */
        .status-bar {
            height: 32px;
            border-top: 1px solid var(--border);
            background: #1c1c1c;
            display: flex;
            align-items: center;
            padding: 0 16px;
            font-size: 11px;
            font-weight: 500;
            flex-shrink: 0;
        }

        .status-bar.success {
            color: var(--green);
            background: rgba(78, 201, 148, 0.05);
        }

        .status-bar.error {
            color: var(--red);
            background: rgba(244, 71, 71, 0.05);
        }

        .status-bar.neutral {
            color: var(--text-muted);
        }

        /* Tree View Panel */
        .tree-panel {
            background: var(--preview-bg);
        }

        .tree-container {
            flex: 1;
            overflow: auto;
            padding: 20px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            line-height: 1.6;
        }

        .tree-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .tree-actions button {
            background: transparent;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 12px;
            padding: 4px;
            transition: color 0.15s;
        }

        .tree-actions button:hover {
            color: var(--text-bright);
        }

        /* JSON Tree styling elements */
        .tree-group {
            position: relative;
        }

        .tree-group-header {
            display: flex;
            align-items: center;
            cursor: pointer;
            user-select: none;
        }

        .tree-toggle {
            color: var(--text-muted);
            width: 16px;
            height: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 4px;
            font-size: 8px;
            transition: transform 0.15s ease, color 0.15s;
            cursor: pointer;
        }

        .tree-toggle:hover {
            color: var(--text-bright);
        }

        .tree-group-body {
            list-style: none;
            padding-left: 20px;
            border-left: 1px dashed #3a3a3a;
            margin-left: 8px;
        }

        .tree-group-body.hidden {
            display: none;
        }

        .json-collapse-preview {
            display: none;
            color: var(--text-muted);
            font-size: 11px;
            margin: 0 4px;
            user-select: none;
        }

        .tree-group.collapsed > .tree-group-body {
            display: none;
        }

        .tree-group.collapsed > .tree-group-header .json-collapse-preview {
            display: inline;
        }

        .json-key {
            color: var(--blue);
            font-weight: 500;
        }

        .json-key.highlight {
            background: rgba(255, 204, 2, 0.25);
            color: var(--text-bright);
            outline: 1px dashed var(--accent);
            border-radius: 2px;
        }

        .json-bracket {
            color: #d4d4d4;
            font-weight: bold;
        }

        .json-value {
            font-weight: 400;
        }

        .json-string {
            color: var(--orange);
        }

        .json-number {
            color: #b5cea8;
        }

        .json-boolean {
            color: var(--purple);
        }

        .json-null {
            color: var(--red);
            font-weight: bold;
        }

        .tree-line {
            padding-left: 20px;
        }

        .empty-tree {
            color: var(--text-muted);
            text-align: center;
            padding-top: 40px;
            font-family: 'Inter', sans-serif;
        }

        /* Search Input */
        .search-box-tree {
            background: var(--input-bg);
            border: 1px solid var(--border);
            color: var(--text-main);
            padding: 4px 8px;
            font-family: 'Inter', sans-serif;
            font-size: 11px;
            border-radius: 3px;
            outline: none;
            width: 140px;
            transition: border-color 0.15s, width 0.15s ease;
        }

        .search-box-tree:focus {
            border-color: var(--accent);
            width: 180px;
        }

        .hidden {
            display: none !important;
        }

        /* View Mode Selector */
        .view-toggle {
            display: flex;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 1px;
            margin-right: 8px;
            height: 28px;
            align-items: center;
        }

        .toggle-btn {
            background: none;
            border: none;
            color: var(--text-muted);
            padding: 0 10px;
            border-radius: 3px;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            font-size: 11px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.15s ease;
            height: 24px;
        }

        .toggle-btn:hover {
            color: var(--text-main);
        }

        .toggle-btn.active {
            background: var(--accent-dim);
            color: var(--accent);
            font-weight: 600;
        }

        /* Preview Mode Tabs */
        .preview-mode-tabs {
            display: flex;
            gap: 12px;
        }

        .preview-tab {
            cursor: pointer;
            color: var(--text-muted);
            padding: 4px 8px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid transparent;
            transition: all 0.15s ease;
            user-select: none;
        }

        .preview-tab:hover {
            color: var(--text-main);
        }

        .preview-tab.active {
            color: var(--accent);
            border-bottom-color: var(--accent);
            font-weight: 700;
        }

        #plainTextContent {
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            line-height: 1.8;
            padding: 24px;
            color: var(--text-bright);
            overflow: auto;
            flex: 1;
        }

        .plain-text-line {
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            word-break: break-word;
        }

        .plain-text-line:last-child {
            border-bottom: none;
        }

        .empty-plain-text {
            color: var(--text-muted);
            text-align: center;
            padding-top: 40px;
            font-style: italic;
        }

        /* Toast notifications */
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
            font-family: 'Inter', sans-serif;
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

        #toast.error {
            background: #3a1a1a;
            color: #ff8080;
            border-left-color: var(--red);
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
    <input type="file" id="fileInput" accept=".json" class="hidden">

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

    <!-- Top Toolbar -->
    <div class="toolbar">
        <div class="logo-section">
            <i class="fa-solid fa-square-poll-horizontal"></i>
            <h1>Czytnik i Edytor JSON</h1>
            <span class="file-info" id="fileNameDisplay">nowy.json</span>
        </div>

        <div class="actions-section">
            <div class="tb-group">
                <button class="btn " id="btnNew" title="Nowy pusty JSON"><i class="fa-solid fa-file-circle-plus"></i> <span class="lbl">Nowy</span></button>
                <button class="btn btn-primary" id="btnLoad" title="Otwórz plik z dysku"><i class="fa-solid fa-folder-open"></i> <span class="lbl">Otwórz</span></button>
                <button class="btn " id="btnRefresh" title="Odśwież treść z dysku"><i class="fa-solid fa-rotate"></i> <span class="lbl">Odśwież</span></button>
                <button class="btn btn-success" id="btnSave" title="Zapisz na dysku"><i class="fa-solid fa-download"></i> <span class="lbl">Zapisz</span></button>
            </div>
            <div class="tb-sep"></div>
            <div class="tb-group">
                <button class="btn " id="btnFormat" title="Sformatuj JSON"><i class="fa-solid fa-indent"></i> <span class="lbl">Formatuj</span></button>
                <button class="btn " id="btnMinify" title="Zminifikuj JSON"><i class="fa-solid fa-compress"></i> <span class="lbl">Minifikuj</span></button>
            </div>
            <div class="tb-sep"></div>
            <div class="view-toggle">
                <button class="toggle-btn" id="toggleEdit" title="Tylko edycja"><i class="fa-solid fa-code"></i> Edycja</button>
                <button class="toggle-btn active" id="toggleSplit" title="Podzielony widok"><i class="fa-solid fa-columns"></i> Split</button>
                <button class="toggle-btn" id="togglePreview" title="Tylko podgląd"><i class="fa-solid fa-eye"></i> Podgląd</button>
            </div>
        </div>
    </div>

        <!-- Workspace -->
    <div class="workspace">
        <!-- Drag & Drop Zone -->
        <div class="dropzone" id="dropzone">
            <div class="welcome-card">
                <div class="welcome-badge"><i class="fa-solid fa-code"></i></div>
                <h2>Czytnik i Edytor JSON</h2>
                <p class="lead">Otwórz plik <b>.json</b>, aby przeglądać go jako drzewo, walidować, formatować i minifikować.</p>
                <div class="drop-target" id="dropTarget">
                    <i class="fa-solid fa-arrow-up-from-bracket"></i>
                    <b>Przeciągnij plik tutaj</b> lub kliknij, aby wybrać z dysku
                </div>
                <div class="welcome-actions">
                    <button class="btn btn-primary" id="btnPick"><i class="fa-solid fa-folder-open"></i> Wybierz plik</button>
                    <button class="btn" id="btnStartEmpty"><i class="fa-solid fa-file-circle-plus"></i> Nowy JSON</button>
                </div>
                <div class="welcome-foot">
                    <span><i class="fa-solid fa-lock"></i>Plik nie opuszcza Twojego komputera</span>
                    <span><i class="fa-solid fa-sitemap"></i>Drzewo i walidacja</span>
                    <span><i class="fa-solid fa-wand-magic-sparkles"></i>Formatuj / minifikuj</span>
                </div>
            </div>
        </div>

        <!-- Left Panel: Editor -->
        <div class="panel editor-panel" id="editorPanel">
            <div class="panel-header">
                <span>Edytor JSON</span>
                <span id="charCount">Znaki: 0 | Linie: 1</span>
            </div>
            <div class="editor-wrapper">
                <div class="line-numbers" id="lineNumbers">
                    <div class="line-number-item">1</div>
                </div>
                <textarea class="editor-textarea" id="editorTextarea" placeholder="Wklej lub wpisz JSON tutaj..." spellcheck="false"></textarea>
            </div>
            <div class="status-bar neutral" id="validationStatus">
                <span><i class="fa-solid fa-info-circle"></i> Wpisz JSON</span>
            </div>
        </div>

        <!-- Right Panel: Visual Tree View / Preview -->
        <div class="panel tree-panel" id="treePanel">
            <div class="panel-header">
                <div class="preview-mode-tabs">
                    <span class="preview-tab active" id="tabActualJson" title="Rozwijalne drzewo JSON">Faktyczny JSON</span>
                    <span class="preview-tab" id="tabNoTags" title="Same wartości tekstowe/liczbowe">Treść bez tagów</span>
                </div>
                <div class="tree-actions" id="treeActionsContainer">
                    <input type="text" class="search-box-tree" id="searchTree" placeholder="Szukaj klucza..." autocomplete="off">
                    <button id="btnExpandAll" title="Rozwiń wszystko"><i class="fa-solid fa-folder-open"></i></button>
                    <button id="btnCollapseAll" title="Zwiń wszystko"><i class="fa-solid fa-folder"></i></button>
                </div>
            </div>
            <div class="tree-container" id="treeContent">
                <!-- Tree items dynamically injected here -->
            </div>
            <div class="hidden" id="plainTextContent">
                <!-- Plain text content injected here -->
            </div>
        </div>
    </div>

    <script>
        // DOM Elements
        const fileInput = document.getElementById('fileInput');
        const btnNew = document.getElementById('btnNew');
        const btnLoad = document.getElementById('btnLoad');
        const btnFormat = document.getElementById('btnFormat');
        const btnMinify = document.getElementById('btnMinify');
        const btnSave = document.getElementById('btnSave');
        const btnStartEmpty = document.getElementById('btnStartEmpty');
        const dropzone = document.getElementById('dropzone');
        const editorTextarea = document.getElementById('editorTextarea');
        const lineNumbers = document.getElementById('lineNumbers');
        const validationStatus = document.getElementById('validationStatus');
        const treeContent = document.getElementById('treeContent');
        const fileNameDisplay = document.getElementById('fileNameDisplay');
        const charCount = document.getElementById('charCount');
        const searchTree = document.getElementById('searchTree');
        const btnExpandAll = document.getElementById('btnExpandAll');
        const btnCollapseAll = document.getElementById('btnCollapseAll');
        const confirmModal = document.getElementById('confirmModal');
        const toast = document.getElementById('toast');
        const toastMsg = document.getElementById('toast-msg');

        // New view switcher elements
        const toggleEdit = document.getElementById('toggleEdit');
        const toggleSplit = document.getElementById('toggleSplit');
        const togglePreview = document.getElementById('togglePreview');
        const editorPanel = document.getElementById('editorPanel');
        const treePanel = document.getElementById('treePanel');

        // New preview mode tabs
        const tabActualJson = document.getElementById('tabActualJson');
        const tabNoTags = document.getElementById('tabNoTags');
        const plainTextContent = document.getElementById('plainTextContent');
        const treeActionsContainer = document.getElementById('treeActionsContainer');

        const PICKER_TYPES = { description: 'JSON', accept: { 'application/json': ['.json'] } };
        const applyText = (t) => { editorTextarea.value = t; processAndValidateJSON(); };
        let loadedFileName = 'nowy.json';
        let activePreviewTab = 'json'; // 'json' or 'notags'

        // Default JSON Template/Tutorial
        const defaultJSON = `{
    "witamy": "Czytnik i Edytor JSON 🚀",
    "status": "aktywny",
    "wersja": 3.0,
    "funkcje": [
        "Wczytywanie lokalnych plików (przycisk lub przeciągnij i upuść)",
        "Walidacja składni w czasie rzeczywistym",
        "Podświetlanie błędnej linii bezpośrednio w edytorze",
        "Przycisk szybkiego formatowania i minifikacji kodu",
        "Interaktywny podgląd struktury w formie drzewa",
        "Wyszukiwanie konkretnych kluczy w drzewie podglądu"
    ],
    "wskazowki": {
        "formatowanie": "Przycisk 'Formatuj' w górnym pasku ułoży ładnie kod.",
        "drzewo": "Klikaj ikony strzałek przy { i [, aby zwijać i rozwijać grupy.",
        "szukanie": "Wpisz poszukiwany klucz w polu wyszukiwania po prawej stronie, aby go podświetlić."
    },
    "serwer": "MrPrompt Panel Zarządzania XAMPP"
}`;

        // Helper to escape HTML characters
        function escapeHtml(text) {
            if (typeof text !== 'string') return text;
            return text
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // Custom Confirm Dialog (Nie-systemowe)
        function showConfirm(message, title, onYes) {
            const modal = document.getElementById('confirmModal');
            document.getElementById('confirmTitle').textContent = title || 'Potwierdzenie';
            document.getElementById('confirmMessage').innerHTML = message;
            modal.classList.add('active');
            
            document.getElementById('btnConfirmYes').onclick = () => {
                onYes();
                modal.classList.remove('active');
            };
            
            document.getElementById('btnConfirmNo').onclick = () => {
                modal.classList.remove('active');
            };
        }

        // Custom Toast Notification
        function showToast(message, type = 'info') {
            toastMsg.textContent = message;
            toast.className = ''; // Reset classes
            if (type === 'success') {
                toast.classList.add('success');
                toast.innerHTML = `<i class="fa-solid fa-circle-check"></i> <span id="toast-msg">${message}</span>`;
            } else if (type === 'error') {
                toast.classList.add('error');
                toast.innerHTML = `<i class="fa-solid fa-circle-xmark"></i> <span id="toast-msg">${message}</span>`;
            } else {
                toast.innerHTML = `<i class="fa-solid fa-info-circle"></i> <span id="toast-msg">${message}</span>`;
            }
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        // Find error line number from standard browser JSON error message
        function getErrorLineNumber(error, text) {
            // Chrome format: "Unexpected token } in JSON at position X"
            const matchPos = error.message.match(/at position (\d+)/);
            if (matchPos) {
                const pos = parseInt(matchPos[1], 10);
                const sub = text.substring(0, pos);
                return sub.split('\n').length;
            }
            // Firefox/Safari format: "JSON.parse: unexpected character at line X column Y"
            const matchLine = error.message.match(/line (\d+)/);
            if (matchLine) {
                return parseInt(matchLine[1], 10);
            }
            return null;
        }

        // Render line numbers in the gutter
        function updateLineNumbers(errorLine = null) {
            const lines = editorTextarea.value.split('\n').length;
            let html = '';
            for (let i = 1; i <= lines; i++) {
                if (i === errorLine) {
                    html += `<div class="line-number-item line-error" title="Błąd w tej linii">${i}</div>`;
                } else {
                    html += `<div class="line-number-item">${i}</div>`;
                }
            }
            lineNumbers.innerHTML = html;
            charCount.textContent = `Znaki: ${editorTextarea.value.length} | Linie: ${lines}`;
        }

        // Recursive JSON object/array to HTML Tree conversion
        function jsonToHtml(value, key = null, isLast = true) {
            const type = typeof value;
            let html = '';

            const indentClass = key !== null ? 'tree-node' : '';
            const keySpan = key !== null ? `<span class="json-key" data-key="${escapeHtml(key)}">"${escapeHtml(key)}"</span>: ` : '';

            if (value === null) {
                html += `<div class="tree-line ${indentClass}">${keySpan}<span class="json-value json-null">null</span>${isLast ? '' : ','}</div>`;
            } else if (Array.isArray(value)) {
                const isEmpty = value.length === 0;
                const toggle = isEmpty ? '' : '<span class="tree-toggle expanded"><i class="fa-solid fa-chevron-down"></i></span>';
                html += `<div class="tree-group ${indentClass}">
                    <div class="tree-group-header">${toggle}${keySpan}<span class="json-bracket">[</span><span class="json-collapse-preview">${isEmpty ? '' : '...'}</span></div>
                    ${isEmpty ? '' : `<ul class="tree-group-body">
                        ${value.map((item, index) => `<li>${jsonToHtml(item, null, index === value.length - 1)}</li>`).join('')}
                    </ul>`}
                    <div class="tree-group-footer"><span class="json-bracket">]</span>${isLast ? '' : ','}</div>
                </div>`;
            } else if (type === 'object') {
                const keys = Object.keys(value);
                const isEmpty = keys.length === 0;
                const toggle = isEmpty ? '' : '<span class="tree-toggle expanded"><i class="fa-solid fa-chevron-down"></i></span>';
                html += `<div class="tree-group ${indentClass}">
                    <div class="tree-group-header">${toggle}${keySpan}<span class="json-bracket">{</span><span class="json-collapse-preview">${isEmpty ? '' : '...'}</span></div>
                    ${isEmpty ? '' : `<ul class="tree-group-body">
                        ${keys.map((k, index) => `<li>${jsonToHtml(value[k], k, index === keys.length - 1)}</li>`).join('')}
                    </ul>`}
                    <div class="tree-group-footer"><span class="json-bracket">}</span>${isLast ? '' : ','}</div>
                </div>`;
            } else if (type === 'string') {
                html += `<div class="tree-line ${indentClass}">${keySpan}<span class="json-value json-string">"${escapeHtml(value)}"</span>${isLast ? '' : ','}</div>`;
            } else if (type === 'number') {
                html += `<div class="tree-line ${indentClass}">${keySpan}<span class="json-value json-number">${value}</span>${isLast ? '' : ','}</div>`;
            } else if (type === 'boolean') {
                html += `<div class="tree-line ${indentClass}">${keySpan}<span class="json-value json-boolean">${value}</span>${isLast ? '' : ','}</div>`;
            }

            return html;
        }

        // Recursively extract all values (without keys/tags) from JSON
        function extractTextValues(value) {
            const type = typeof value;
            if (value === null) return '';
            
            if (type === 'string' || type === 'number' || type === 'boolean') {
                return `<div class="plain-text-line">${escapeHtml(String(value))}</div>`;
            }
            if (Array.isArray(value)) {
                return value.map(item => extractTextValues(item)).join('');
            }
            if (type === 'object') {
                return Object.keys(value).map(k => extractTextValues(value[k])).join('');
            }
            return '';
        }

        // Validate text, render tree, and render plain text content
        function processAndValidateJSON() {
            const rawText = editorTextarea.value;
            
            if (rawText.trim() === '') {
                treeContent.innerHTML = '<div class="empty-tree">Pusty edytor. Wpisz JSON lub wczytaj plik.</div>';
                plainTextContent.innerHTML = '<div class="empty-plain-text">Brak danych - pusty edytor.</div>';
                validationStatus.innerHTML = '<span><i class="fa-solid fa-info-circle"></i> Wpisz JSON</span>';
                validationStatus.className = 'status-bar neutral';
                updateLineNumbers(null);
                return;
            }

            try {
                const parsed = JSON.parse(rawText);
                
                // Render tree view
                treeContent.innerHTML = jsonToHtml(parsed);
                
                // Render text values without tags
                const plainHtml = extractTextValues(parsed);
                plainTextContent.innerHTML = plainHtml || '<div class="empty-plain-text">Brak wartości tekstowych w strukturze JSON.</div>';
                
                validationStatus.innerHTML = '<span><i class="fa-solid fa-circle-check"></i> JSON jest poprawny</span>';
                validationStatus.className = 'status-bar success';
                
                // Clear any error lines
                updateLineNumbers(null);
                
                // Apply current search filter to new tree if exists
                if (searchTree.value.trim() !== '') {
                    filterTree(searchTree.value);
                }
            } catch (error) {
                const errLine = getErrorLineNumber(error, rawText);
                validationStatus.innerHTML = `<span><i class="fa-solid fa-circle-xmark"></i> Błąd walidacji: ${escapeHtml(error.message)} (Linia ${errLine || 'nieznana'})</span>`;
                validationStatus.className = 'status-bar error';
                
                plainTextContent.innerHTML = `<div class="empty-plain-text" style="color:var(--red);"><i class="fa-solid fa-circle-xmark"></i> Błąd walidacji JSON. Popraw błędy w edytorze.</div>`;
                
                // Redraw line numbers and highlight the error line
                updateLineNumbers(errLine);
            }
        }

        // Load file contents
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

        // Format JSON text
        function formatJSON() {
            const text = editorTextarea.value;
            if (!text.trim()) return;

            try {
                const parsed = JSON.parse(text);
                editorTextarea.value = JSON.stringify(parsed, null, 4);
                processAndValidateJSON();
                showToast('Sformatowano kod JSON', 'success');
            } catch (e) {
                showToast('Nie można sformatować - niepoprawna składnia JSON!', 'error');
            }
        }

        // Minify JSON text
        function minifyJSON() {
            const text = editorTextarea.value;
            if (!text.trim()) return;

            try {
                const parsed = JSON.parse(text);
                editorTextarea.value = JSON.stringify(parsed);
                processAndValidateJSON();
                showToast('Zminimalizowano kod JSON', 'success');
            } catch (e) {
                showToast('Nie można zminimalizować - niepoprawna składnia JSON!', 'error');
            }
        }

        // Save file to PC
        function saveFile() {
            const text = editorTextarea.value;
            if (!text.trim()) {
                showToast('Brak zawartości do zapisania.', 'error');
                return;
            }

            try {
                JSON.parse(text);
            } catch (e) {
                showToast('Nie można zapisać niepoprawnego kodu JSON!', 'error');
                return;
            }

            const blob = new Blob([text], { type: 'application/json;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = loadedFileName;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            showToast('Zapisano plik na dysku!', 'success');
        }

        // Tree filter (Search logic)
        function filterTree(query) {
            const cleanQuery = query.trim().toLowerCase();
            const keys = document.querySelectorAll('.json-key');
            
            keys.forEach(keySpan => {
                const text = keySpan.textContent.replace(/"/g, '').toLowerCase();
                if (cleanQuery && text.includes(cleanQuery)) {
                    keySpan.classList.add('highlight');
                    
                    // Automatically expand parent elements to reveal the result
                    let parent = keySpan.closest('.tree-group');
                    while (parent) {
                        if (parent.classList.contains('collapsed')) {
                            parent.classList.remove('collapsed');
                            const icon = parent.querySelector('.tree-toggle i');
                            if (icon) {
                                icon.className = 'fa-solid fa-chevron-down';
                                parent.querySelector('.tree-toggle').classList.add('expanded');
                                parent.querySelector('.tree-toggle').classList.remove('collapsed');
                            }
                        }
                        parent = parent.parentElement.closest('.tree-group');
                    }
                } else {
                    keySpan.classList.remove('highlight');
                }
            });
        }

        // --- Event Listeners ---

        // File Selection triggers
        btnLoad.addEventListener('click', openFilePicker);
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                loadFile(e.target.files[0]);
                e.target.value = '';
            }
        });

        // Save trigger
        btnSave.addEventListener('click', saveFile);

        // Formatting triggers
        btnFormat.addEventListener('click', formatJSON);
        btnMinify.addEventListener('click', minifyJSON);

        // New JSON trigger
        btnNew.addEventListener('click', () => {
            showConfirm(
                'Czy na pewno chcesz utworzyć nowy projekt JSON?<br><span style="color:var(--red);">Wszelkie niezapisane zmiany zostaną bezpowrotnie utracone.</span>',
                'Nowy JSON',
                () => {
                    editorTextarea.value = '{\n    \n}';
                    fileHandle = null; lastFile = null; lastText = ''; btnRefresh.disabled = true;
                    loadedFileName = 'nowy.json';
                    fileNameDisplay.textContent = 'nowy.json';
                    dropzone.classList.add('hidden');
                    processAndValidateJSON();
                    showToast('Utworzono nowy, pusty dokument JSON', 'success');
                }
            );
        });

        // Start empty from drag & drop
        btnStartEmpty.addEventListener('click', (e) => {
            e.stopPropagation();
            dropzone.classList.add('hidden');
            editorTextarea.value = '{\n    \n}';
            fileHandle = null; lastFile = null; lastText = ''; btnRefresh.disabled = true;
            loadedFileName = 'nowy.json';
            fileNameDisplay.textContent = 'nowy.json';
            processAndValidateJSON();
        });

        // Sync editor textarea scrolling with line numbers gutter scrolling
        editorTextarea.addEventListener('scroll', () => {
            lineNumbers.scrollTop = editorTextarea.scrollTop;
        });

        // Process and validate input
        editorTextarea.addEventListener('input', () => {
            processAndValidateJSON();
        });

        // Expand / Collapse all logic
        btnExpandAll.addEventListener('click', () => {
            document.querySelectorAll('.tree-group').forEach(group => {
                group.classList.remove('collapsed');
                const toggle = group.querySelector('.tree-toggle');
                if (toggle) {
                    toggle.classList.add('expanded');
                    toggle.classList.remove('collapsed');
                    const icon = toggle.querySelector('i');
                    if (icon) icon.className = 'fa-solid fa-chevron-down';
                }
            });
        });

        btnCollapseAll.addEventListener('click', () => {
            document.querySelectorAll('.tree-group').forEach(group => {
                group.classList.add('collapsed');
                const toggle = group.querySelector('.tree-toggle');
                if (toggle) {
                    toggle.classList.remove('expanded');
                    toggle.classList.add('collapsed');
                    const icon = toggle.querySelector('i');
                    if (icon) icon.className = 'fa-solid fa-chevron-right';
                }
            });
        });

        // Search trigger
        searchTree.addEventListener('input', (e) => {
            filterTree(e.target.value);
        });

        // Tree node toggling via event delegation
        treeContent.addEventListener('click', (e) => {
            // Find closest tree-toggle or header (if clicked on key/bracket of a collapsible item)
            const toggle = e.target.closest('.tree-toggle');
            if (toggle) {
                const group = toggle.closest('.tree-group');
                const icon = toggle.querySelector('i');
                
                if (toggle.classList.contains('expanded')) {
                    toggle.classList.remove('expanded');
                    toggle.classList.add('collapsed');
                    group.classList.add('collapsed');
                    if (icon) icon.className = 'fa-solid fa-chevron-right';
                } else {
                    toggle.classList.add('expanded');
                    toggle.classList.remove('collapsed');
                    group.classList.remove('collapsed');
                    if (icon) icon.className = 'fa-solid fa-chevron-down';
                }
                return;
            }

            // Fallback to clicking group header
            const header = e.target.closest('.tree-group-header');
            if (header) {
                const grpToggle = header.querySelector('.tree-toggle');
                if (grpToggle) {
                    grpToggle.click();
                }
            }
        });

        // Drag & Drop event handling
        document.getElementById('dropTarget').addEventListener('click', openFilePicker);
        document.getElementById('btnPick').addEventListener('click', openFilePicker);

        // Ekran startowy pojawia się na czas przeciągania pliku i wraca do poprzedniego stanu
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
            const it = e.dataTransfer.items && e.dataTransfer.items[0];
            const hp = it && it.getAsFileSystemHandle ? it.getAsFileSystemHandle() : null;
            endDrag();
            if (e.dataTransfer.files.length > 0) {
                const file = e.dataTransfer.files[0];
                if (/\.json$/i.test(file.name)) {
                    Promise.resolve(hp).then(h => loadFile(file, h && h.kind === 'file' ? h : null), () => loadFile(file));
                } else {
                    showToast('Obsługiwane są wyłącznie pliki z rozszerzeniem .json!', 'error');
                }
            }
        });

        // --- View Mode switching logic ---
        toggleEdit.addEventListener('click', () => {
            setActiveViewBtn(toggleEdit);
            editorPanel.classList.remove('hidden');
            treePanel.classList.add('hidden');
        });

        toggleSplit.addEventListener('click', () => {
            setActiveViewBtn(toggleSplit);
            editorPanel.classList.remove('hidden');
            treePanel.classList.remove('hidden');
        });

        togglePreview.addEventListener('click', () => {
            setActiveViewBtn(togglePreview);
            editorPanel.classList.add('hidden');
            treePanel.classList.remove('hidden');
        });

        function setActiveViewBtn(activeBtn) {
            [toggleEdit, toggleSplit, togglePreview].forEach(btn => btn.classList.remove('active'));
            activeBtn.classList.add('active');
        }

        // --- Preview Mode Tabs switching logic ---
        function switchPreviewMode(mode) {
            activePreviewTab = mode;
            if (mode === 'json') {
                tabActualJson.classList.add('active');
                tabNoTags.classList.remove('active');
                
                treeContent.classList.remove('hidden');
                plainTextContent.classList.add('hidden');
                treeActionsContainer.classList.remove('hidden');
            } else {
                tabActualJson.classList.remove('active');
                tabNoTags.classList.add('active');
                
                treeContent.classList.add('hidden');
                plainTextContent.classList.remove('hidden');
                treeActionsContainer.classList.add('hidden');
            }
        }

        tabActualJson.addEventListener('click', () => switchPreviewMode('json'));
        tabNoTags.addEventListener('click', () => switchPreviewMode('notags'));

        // Initialize editor with template content
        editorTextarea.value = defaultJSON;
        processAndValidateJSON();
    </script>
</body>
</html>
