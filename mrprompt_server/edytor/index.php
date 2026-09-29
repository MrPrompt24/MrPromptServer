<?php require_once __DIR__ . '/../security.php'; ?>
<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <?= mrp_client_script() ?>
    <script>window.MRP_DOCROOT = <?= json_encode(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'])) ?>;</script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MrPrompt Editor</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/review.css">
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/xterm@5.3.0/css/xterm.css" />
    <script src="https://cdn.jsdelivr.net/npm/xterm@5.3.0/lib/xterm.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xterm-addon-fit@0.8.0/lib/xterm-addon-fit.js"></script>
</head>

<body>

    <!-- HEADER -->
    <header>
        <div class="logo">
            <span style="font-size:16px;"><img src="favicon.png"></span>
            <span>
                <font color="white">Mr</font>
                <font color="red">Prompt</font> Editor
            </span>
        </div>
        <div class="menu-bar">
            <div class="menu-item" onclick="toggleMenu('file-menu')">
                Plik
                <div class="dropdown-menu" id="file-menu">
                    <div class="dropdown-item" onclick="createNewTab()">Nowy Plik <span class="shortcut">Ctrl+N</span></div>
                    <div class="dropdown-item" onclick="triggerOpenFile()">Otwórz Plik... <span class="shortcut">Ctrl+O</span></div>
                    <div class="dropdown-item" onclick="triggerOpenFolder()">Otwórz Folder...</div>
                    <div class="dropdown-divider"></div>
                    <div class="dropdown-item" onclick="saveCurrentFile()">Zapisz <span class="shortcut">Ctrl+S</span></div>
                    <div class="dropdown-item" onclick="saveAsCurrentFile()">Zapisz Jako... <span class="shortcut">Ctrl+Shift+S</span></div>
                    <div class="dropdown-divider"></div>
                    <div class="dropdown-item" onclick="closeCurrentTab()">Zamknij Kartę <span class="shortcut">Ctrl+W</span></div>
                </div>
            </div>
            <div class="menu-item" onclick="toggleMenu('edit-menu')">
                Edycja
                <div class="dropdown-menu" id="edit-menu">
                    <div class="dropdown-item" onclick="triggerAction('undo')">Cofnij <span class="shortcut">Ctrl+Z</span></div>
                    <div class="dropdown-item" onclick="triggerAction('redo')">Ponów <span class="shortcut">Ctrl+Y</span></div>
                    <div class="dropdown-divider"></div>
                    <div class="dropdown-item" onclick="triggerAction('editor.action.clipboardCutAction')">Wytnij <span class="shortcut">Ctrl+X</span></div>
                    <div class="dropdown-item" onclick="triggerAction('editor.action.clipboardCopyAction')">Kopiuj <span class="shortcut">Ctrl+C</span></div>
                    <div class="dropdown-item" onclick="triggerAction('editor.action.clipboardPasteAction')">Wklej <span class="shortcut">Ctrl+V</span></div>
                    <div class="dropdown-divider"></div>
                    <div class="dropdown-item" onclick="triggerAction('actions.find')">Znajdź <span class="shortcut">Ctrl+F</span></div>
                    <div class="dropdown-item" onclick="triggerAction('editor.action.startFindReplaceAction')">Zamień <span class="shortcut">Ctrl+H</span></div>
                </div>
            </div>
            <div class="menu-item" onclick="toggleMenu('view-menu')">
                Widok
                <div class="dropdown-menu" id="view-menu">
                    <div class="dropdown-item" onclick="toggleSidebar()">Pasek Boczny</div>
                    <div class="dropdown-item" onclick="toggleWordWrap()">Zawijanie Wierszy</div>
                    <div class="dropdown-divider"></div>
                    <div class="dropdown-item" onclick="toggleLivePreview()">Podgląd na żywo</div>
                </div>
            </div>
            <div class="menu-item" onclick="toggleMenu('tools-menu')">
                Narzędzia
                <div class="dropdown-menu" id="tools-menu">
                    <div class="dropdown-item" onclick="openReviewPanel()">Przegląd zmian (diff) <span class="shortcut">Ctrl+Shift+G</span></div>
                    <div class="dropdown-item" onclick="openContextPack()">Paczka kontekstu dla AI...</div>
                    <div class="dropdown-item" onclick="openAiFiles()">Pliki sterujące AI (CLAUDE.md...)</div>
                    <div class="dropdown-divider"></div>
                    <div class="dropdown-item" onclick="runQuick('php_lint')">Sprawdź składnię PHP (bieżący plik)</div>
                    <div class="dropdown-item" onclick="runQuick('php_lint_all')">Sprawdź składnię PHP (cały projekt)</div>
                    <div class="dropdown-item" onclick="runQuick('git_status')">Git: status</div>
                    <div class="dropdown-item" onclick="runQuick('git_log')">Git: ostatnie commity</div>
                    <div class="dropdown-item" onclick="runQuick('npm_test')">npm test</div>
                </div>
            </div>
            <div class="menu-item" onclick="openTerminal()">Terminal</div>
            <div class="menu-item" onclick="toggleMenu('backup-menu')">
                Kopie
                <div class="dropdown-menu" id="backup-menu">
                    <div class="dropdown-item" onclick="openBackupBrowser()">Przeglądaj</div>
                    <div class="dropdown-item" onclick="openBackupSettings()">Ustawienia</div>
                </div>
            </div>
            <div class="menu-item" onclick="toggleMenu('help-menu')">
                Pomoc
                <div class="dropdown-menu" id="help-menu">
                    <div class="dropdown-item" onclick="openHelp()">Jak używać Edytora (pełna pomoc) <span class="shortcut">F1</span></div>
                    <div class="dropdown-item" onclick="openHelp('przeglad')">Przegląd zmian: krótko i prosto</div>
                    <div class="dropdown-divider"></div>
                    <div class="dropdown-item" onclick="showShortcuts()">Skróty klawiszowe</div>
                </div>
            </div>
            <div class="menu-item" onclick="openAboutModal()">O Aplikacji</div>

            <!-- Toolbar Icons -->
            <div class="toolbar-separator"></div>
            <div class="toolbar-icon" title="Cofnij (Ctrl+Z)" onclick="triggerUndo()"><i class="fa-solid fa-rotate-left"></i></div>
            <div class="toolbar-icon" title="Ponów (Ctrl+Y)" onclick="triggerRedo()"><i class="fa-solid fa-rotate-right"></i></div>
            <div class="toolbar-icon" title="Nowy Plik (Ctrl+N)" onclick="createNewTab('Bez tytułu', '')"><i class="fa-solid fa-file-circle-plus"></i></div>
            <div class="toolbar-separator"></div>
            <div class="toolbar-icon" title="Otwórz Plik (Ctrl+O)" onclick="triggerOpenFile()"><i class="fa-regular fa-file"></i></div>
            <div class="toolbar-icon" title="Otwórz Folder" onclick="triggerOpenFolder()"><i class="fa-regular fa-folder"></i></div>
            <div class="toolbar-icon" title="Zapisz (Ctrl+S)" onclick="triggerSaveFile()"><i class="fa-regular fa-floppy-disk"></i></div>
            <div class="toolbar-icon" title="Zapisz Jako (Ctrl+Shift+S)" onclick="triggerSaveAs()"><i class="fa-solid fa-floppy-disk"></i></div>
            <div class="toolbar-icon" title="Przegląd zmian (Ctrl+Shift+G)" onclick="openReviewPanel()"><i class="fa-solid fa-code-compare"></i></div>
            <div class="toolbar-icon" title="Szukaj (Ctrl+Shift+F)" onclick="openSearchModal()"><i class="fa-solid fa-magnifying-glass"></i></div>
            <div class="toolbar-icon" title="Zamień (Ctrl+H)" onclick="triggerReplace()"><i class="fa-solid fa-right-left"></i></div>
        </div>

        <!-- Hidden Inputs -->
        <input type="file" id="fileInput" style="display:none;" onchange="handleFileOpen(this)">
    </header>

    <!-- MAIN CONTAINER -->
    <div class="main-container">
        <!-- SIDEBAR -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <span>Eksplorator</span>
                <span style="display:flex; gap:10px; align-items:center;">
                    <span class="icon" onclick="edNewItem('file')" style="cursor: pointer;" title="Nowy plik w tym folderze"><i class="fa-solid fa-file-circle-plus"></i></span>
                    <span class="icon" onclick="edNewItem('folder')" style="cursor: pointer;" title="Nowy folder w tym folderze"><i class="fa-solid fa-folder-plus"></i></span>
                    <span class="icon" onclick="refreshSidebar()" style="cursor: pointer;" title="Odśwież"><i class="fa-solid fa-rotate"></i></span>
                </span>
            </div>
            <div class="file-explorer">
                <!-- Placeholder for file tree -->
                <div style="padding:10px; color:#888; font-size:12px; text-align:center;">
                    [Drzewo Plików]
                </div>
            </div>
        </aside>

        <!-- EDITOR AREA -->
        <div class="editor-area">
            <div class="tabs-container" id="tabsContainer">
                <!-- Tabs will be injected here via JS -->
            </div>
            <div id="monaco-editor"></div>
            <div id="preview-container" style="display:none;">
                <iframe id="preview-frame" style="width:100%; height:100%; border:none; background:white;"></iframe>
            </div>
            <div id="terminal-container" style="display:none;"></div>
        </div>
    </div>

    <!-- FOOTER -->
    <footer>
        <div class="footer-left">
            <div class="footer-item"><span class="icon"><i class="fa-solid fa-code-branch"></i></span> main</div>
            <div class="footer-item"><span class="icon"><i class="fa-regular fa-circle-xmark"></i></span> 0</div>
            <div class="footer-item"><span class="icon"><i class="fa-solid fa-triangle-exclamation"></i></span> 0</div>
        </div>
        <div class="footer-right">
            <div class="footer-item" id="cursor-position">Ln 1, Col 1</div>
            <div class="footer-item">UTF-8</div>
            <div class="footer-item" id="editor-lang">PHP</div>
            <div class="footer-item"><span class="icon"><i class="fa-regular fa-bell"></i></span></div>
        </div>
    </footer>

    <!-- MODAL -->
    <div id="app-modal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <span id="modal-title">Tytuł</span>
                <span class="modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></span>
            </div>
            <div class="modal-content" id="modal-body">
                Treść
            </div>
            <div class="modal-footer" id="modal-footer">
                <button class="btn btn-primary" onclick="closeModal()">OK</button>
            </div>
        </div>
    </div>

    <!-- SEARCH MODAL -->
    <div id="search-modal" class="modal-overlay">
        <div class="modal" style="width: 600px;">
            <div class="modal-header">
                <span>Szukaj w plikach</span>
                <span class="modal-close" onclick="closeSearchModal()"><i class="fa-solid fa-xmark"></i></span>
            </div>
            <div class="modal-content" style="padding:10px;">
                <input type="text" id="search-input" class="modal-input" placeholder="Wpisz frazę..." style="margin-top:0;" onkeypress="handleSearchKeyPress(event)">
                <div style="margin-top:10px; text-align:right;">
                    <button class="btn btn-primary" onclick="performSearch()">Szukaj</button>
                </div>
                <div id="search-results" style="margin-top:10px; height: 300px; overflow-y: auto; background: #1e1e1e; border: 1px solid #3e3e42;">
                    <!-- Results -->
                </div>
            </div>
        </div>
    </div>

    <!-- CONTEXT MENU -->
    <div id="context-menu" class="dropdown-menu">
        <div class="dropdown-item" onclick="triggerDeleteItem()"><i class="fa-solid fa-trash" style="margin-right:8px; color:#cc0000;"></i> Usuń</div>
    </div>

    <!-- SCRIPTS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs/loader.min.js"></script>
    <script src="js/app.js"></script>
    <script src="js/review.js"></script>
</body>

</html>