// Initial Logic
require.config({ paths: { 'vs': 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs' } });

let editor;
let tabs = [];
let activeTabId = null;
let suppressChange = false; // true podczas programowej zmiany treści (nie oznacza karty jako zmodyfikowanej)
let fileHandlerUrl = '../file_handler.php'; // Adres backendu

require(['vs/editor/editor.main'], function () {

    // Initialize Editor with empty content
    editor = monaco.editor.create(document.getElementById('monaco-editor'), {
        value: "",
        language: 'plaintext',
        theme: 'vs-dark', // Dark Theme
        automaticLayout: true,
        fontSize: 14,
        minimap: {
            enabled: true
        },
        scrollBeyondLastLine: false,
        lineNumbers: "on",
        renderWhitespace: "selection",
        fontFamily: "'Consolas', 'Courier New', monospace"
    });

    // Update Footer Info
    editor.onDidChangeCursorPosition((e) => {
        document.getElementById('cursor-position').innerText = `Ln ${e.position.lineNumber}, Col ${e.position.column}`;
    });

    editor.onDidChangeModelContent(() => {
        if (suppressChange) return;
        const tab = tabs.find(t => t.id === activeTabId);
        if (tab) {
            tab.content = editor.getValue();
            if (!tab.modified) {
                tab.modified = true;
                renderTabs();
            }
        }
    });

    // Start with one empty tab
    createNewTab('Untitled-1');

    // Window Resize Handler
    window.addEventListener('resize', () => {
        editor.layout();
    });

    // Close menu when clicking outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.menu-item')) {
            document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('show'));
        }
    });

    // Shortcuts
    document.addEventListener('keydown', function (e) {
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            saveCurrentFile();
        }
        if (e.ctrlKey && e.key === 'o') {
            e.preventDefault();
            triggerOpenFile();
        }
        if (e.ctrlKey && e.shiftKey && e.key === 'F') {
            e.preventDefault();
            openSearchModal();
        }
    });

    // Initialize Sidebar
    if (window.mrpStartup) window.mrpStartup(); // folder projektu, ?open=, ?panel= (review.js)
    else loadSidebar(window.MRP_DOCROOT || 'C:/xampp/htdocs');

    // Start Footer Updates
    startFooterUpdates();

    // Register IntelliSense
    registerIntelliSense();

    // Drag & Drop (Desktop -> Editor)
    document.addEventListener('drop', (e) => {
        e.preventDefault();
        e.stopPropagation();

        if (e.dataTransfer.files.length > 0) {
            Array.from(e.dataTransfer.files).forEach(file => {
                const reader = new FileReader();
                reader.onload = function (event) {
                    createNewTab(file.name, event.target.result);
                };
                reader.readAsText(file);
            });
        }
    });

    document.addEventListener('dragover', (e) => {
        e.preventDefault();
        e.stopPropagation();
    });
});


// --- TAB MANAGEMENT ---

function createNewTab(name = null, content = "") {
    const id = Date.now();
    const tabName = name || `Untitled-${tabs.length + 1}`;

    const newTab = {
        id: id,
        name: tabName,
        content: content,
        language: 'plaintext',
        path: null, // Full system path if saved/open
        modified: false
    };

    tabs.push(newTab);
    activateTab(id);
}

function activateTab(id) {
    activeTabId = id;
    const tab = tabs.find(t => t.id === id);

    // Update Editor
    if (editor) {
        suppressChange = true;
        editor.setValue(tab.content);
        suppressChange = false;
        updateEditorLanguage(tab.name);
    }

    renderTabs();
    if (window.rvOnActivate) window.rvOnActivate(tab);
}

function updateEditorLanguage(filename) {
    let lang = 'plaintext';
    if (filename.endsWith('.php')) lang = 'php';
    else if (filename.endsWith('.js')) lang = 'javascript';
    else if (filename.endsWith('.css')) lang = 'css';
    else if (filename.endsWith('.html')) lang = 'html';
    else if (filename.endsWith('.json')) lang = 'json';
    else if (filename.endsWith('.md')) lang = 'markdown';

    monaco.editor.setModelLanguage(editor.getModel(), lang);
    document.getElementById('editor-lang').innerText = lang.toUpperCase();
}

function closeTab(id, event) {
    if (event) event.stopPropagation();

    const index = tabs.findIndex(t => t.id === id);
    if (index === -1) return;

    // Check modified? (Simplified for now)

    tabs.splice(index, 1);

    if (tabs.length === 0) {
        createNewTab();
    } else {
        if (activeTabId === id) {
            const newActive = tabs[index - 1] || tabs[0];
            activateTab(newActive.id);
        } else {
            renderTabs();
        }
    }
}

function closeCurrentTab() {
    if (activeTabId) closeTab(activeTabId);
}

function renderTabs() {
    const container = document.getElementById('tabsContainer');
    // Save the + button or recreate it
    container.innerHTML = '';

    tabs.forEach(tab => {
        const div = document.createElement('div');
        div.className = `tab ${tab.id === activeTabId ? 'active' : ''}`;
        div.innerHTML = `
            <span class="icon" style="color: ${getIconColor(tab.name)}; margin-right:5px;">
                ${getIcon(tab.name)}
            </span>
            <span>${String(tab.name).replace(/&/g, '&amp;').replace(/</g, '&lt;')}${tab.modified ? ' ●' : ''}${tab.reloaded ? ' <i class="fa-solid fa-rotate rv-tab-reload" title="Odświeżone z dysku"></i>' : ''}</span>
            <span class="tab-close" onclick="closeTab(${tab.id}, event)"><i class="fa-solid fa-xmark"></i></span>
        `;
        div.onclick = () => activateTab(tab.id);
        container.appendChild(div);
    });

    // Add "+" button
    const addBtn = document.createElement('div');
    addBtn.className = 'tab-add';
    addBtn.innerHTML = '+';
    addBtn.onclick = () => createNewTab();
    container.appendChild(addBtn);
}

// --- FILE IO ---

function triggerOpenFile() {
    document.getElementById('fileInput').click();
}

function handleFileOpen(input) {
    const file = input.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function (e) {
        const content = e.target.result;
        createNewTab(file.name, content);
    };
    reader.readAsText(file);
    input.value = ''; // Reset
}

function saveCurrentFile() {
    const tab = tabs.find(t => t.id === activeTabId);
    if (!tab) return;

    if (tab.path) {
        // Backend Save
        const formData = new FormData();
        formData.append('action', 'save');
        formData.append('path', tab.path);
        formData.append('content', editor.getValue());

        fetch(fileHandlerUrl, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'ok') {
                    tab.modified = false;
                    tab.mtime = undefined;
                    tab.conflict = null;
                    renderTabs();
                    showModal('Sukces', 'Zapisano pomyślnie!');
                } else {
                    showModal('Błąd', 'Błąd zapisu: ' + data.msg);
                }
            });
    } else {
        saveAsCurrentFile();
    }
}

function saveAsCurrentFile() {
    const tab = tabs.find(t => t.id === activeTabId);
    if (!tab) return;

    // Determine start path
    let startPath = browserCurrentPath;
    if (tab.path) {
        // Use dirname of current file
        startPath = tab.path.replace(/[\/\\][^\/\\]*$/, '');
    }

    openSaveAsModal(startPath, tab.name);
}

// --- SAVE AS MODAL SYSTEM ---

let saveBrowserCurrentPath = '';

function openSaveAsModal(path, defaultName) {
    saveBrowserCurrentPath = path;

    const content = `
        <div id="save-browser-container" style="height: 350px; display: flex; flex-direction: column;">
            <div style="display: flex; gap: 5px; margin-bottom: 10px;">
                <input type="text" id="save-browser-path" class="modal-input" style="margin:0; flex:1;" value="${path}" onchange="navigateSaveBrowser(this.value)">
                <button class="btn btn-secondary" onclick="navigateSaveBrowserParent()"><i class="fa-solid fa-arrow-up"></i></button>
            </div>
            <div id="save-browser-list" style="flex: 1; overflow-y: auto; border: 1px solid #3e3e42; background: #1e1e1e; margin-bottom: 10px;">
                <div style="padding:10px; color:#aaa;">Ładowanie...</div>
            </div>
             <div style="display: flex; gap: 5px; align-items: center;">
                <label>Nazwa pliku:</label>
                <input type="text" id="save-filename" class="modal-input" style="margin:0; flex:1;" value="${defaultName}">
            </div>
        </div>
    `;

    const btns = [
        { text: 'Anuluj', class: 'btn-secondary', onclick: 'closeModal()' },
        { text: 'Zapisz', class: 'btn-primary', onclick: 'confirmSaveAs()' }
    ];

    showModal('Zapisz Jako', content, btns);
    fetchSaveDirectoryList(path);
}

function navigateSaveBrowser(path) {
    saveBrowserCurrentPath = path;
    document.getElementById('save-browser-path').value = path;
    fetchSaveDirectoryList(path);
}

function navigateSaveBrowserParent() {
    if (saveBrowserCurrentPath === '') return;
    let parts = saveBrowserCurrentPath.replace(/\\/g, '/').replace(/\/$/, '').split('/');
    if (parts.length > 1) {
        parts.pop();
        let newPath = parts.join('/') + '/';
        navigateSaveBrowser(newPath);
    } else {
        navigateSaveBrowser('');
    }
}

function fetchSaveDirectoryList(path) {
    const listContainer = document.getElementById('save-browser-list');
    if (!listContainer) return;

    const formData = new FormData();
    formData.append('action', 'list_dir');
    formData.append('path', path);

    fetch(fileHandlerUrl, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'ok') {
                listContainer.innerHTML = '';
                const items = data.files.sort((a, b) => {
                    if (a.is_dir === b.is_dir) return a.name.localeCompare(b.name);
                    return a.is_dir ? -1 : 1;
                });

                items.forEach(item => {
                    const div = document.createElement('div');
                    div.className = 'browser-item';

                    let iconHtml = getIcon(item.name, item.is_dir);
                    let colorStyle = item.is_dir ? 'color:#e0ca2c;' : `color:${getIconColor(item.name)};`;

                    div.innerHTML = `<span style="margin-right:8px; ${colorStyle}">${iconHtml}</span> ${item.name}`;

                    if (item.is_dir) {
                        div.style.cursor = 'pointer';
                        div.onclick = () => navigateSaveBrowser(item.path);
                    } else {
                        div.style.cursor = 'pointer';
                        div.onclick = () => {
                            document.getElementById('save-filename').value = item.name;
                        };
                    }

                    listContainer.appendChild(div);
                });
            } else {
                listContainer.innerHTML = `<div style="padding:10px; color:red;">Błąd: ${data.msg}</div>`;
            }
        })
        .catch(err => {
            if (listContainer) listContainer.innerHTML = `<div style="padding:10px; color:red;">Błąd sieci</div>`;
        });
}

function confirmSaveAs() {
    const filename = document.getElementById('save-filename').value;
    if (!filename) {
        showModal('Błąd', "Podaj nazwę pliku!");
        return;
    }

    // Normalize path separator
    let fullPath = saveBrowserCurrentPath;
    if (!fullPath.endsWith('/') && !fullPath.endsWith('\\')) fullPath += '/';
    fullPath += filename;

    const content = editor.getValue();

    const formData = new FormData();
    formData.append('action', 'save');
    formData.append('path', fullPath);
    formData.append('content', content);

    fetch(fileHandlerUrl, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'ok') {
                // Update Tab
                const tab = tabs.find(t => t.id === activeTabId);
                if (tab) {
                    tab.name = filename;
                    tab.path = fullPath; // or data.path from backend
                    tab.modified = false;

                    // Update editor language
                    updateEditorLanguage(filename);

                    renderTabs();
                }
                closeModal();
                showModal('Sukces', 'Zapisano pomyślnie!');
            } else {
                showModal('Błąd', 'Błąd zapisu: ' + data.msg);
            }
        })
        .catch(err => showModal('Błąd', 'Błąd połączenia: ' + err));
}

// --- UTILS ---

function getIcon(name) {
    if (name.endsWith('.php')) return '<i class="fa-brands fa-php"></i>';
    if (name.endsWith('.js')) return '<i class="fa-brands fa-js"></i>';
    if (name.endsWith('.css')) return '<i class="fa-brands fa-css3"></i>';
    if (name.endsWith('.html')) return '<i class="fa-brands fa-html5"></i>';
    return '<i class="fa-regular fa-file"></i>';
}

function getIconColor(name) {
    if (name.endsWith('.php')) return '#777bb4';
    if (name.endsWith('.js')) return '#f1e05a';
    if (name.endsWith('.css')) return '#563d7c';
    if (name.endsWith('.html')) return '#e34c26';
    return '#ccc';
}

// --- MENU ACTIONS ---

function toggleMenu(id) {
    const menu = document.getElementById(id);
    const isShown = menu.classList.contains('show');
    // Hide all first
    document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('show'));

    if (!isShown) {
        menu.classList.add('show');
    }
}

function triggerReplace() {
    if (editor) {
        editor.trigger('source', 'editor.action.startFindReplaceAction');
        document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('show'));
    }
}

function triggerUndo() {
    if (editor) {
        editor.trigger('source', 'undo');
        document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('show'));
    }
}

function triggerRedo() {
    if (editor) {
        editor.trigger('source', 'redo');
        document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('show'));
    }
}

function triggerAction(actionId) {
    if (editor) {
        editor.trigger('menu', actionId);
        document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('show'));
    }
}



let term;
let termFitAddon;
let terminalInitialized = false;
let currentTerminalPath = 'C:/xampp/htdocs/mrprompt_server/edytor/szablon'; // Default start path
let commandBuffer = '';

function openTerminal() {
    const container = document.getElementById('terminal-container');
    if (container.style.display === 'none') {
        container.style.display = 'block';
        if (!terminalInitialized) {
            initTerminal();
        }
        editor.layout(); // Resize editor
    } else {
        container.style.display = 'none';
        editor.layout();
    }
}

function initTerminal() {
    term = new Terminal({
        cursorBlink: true,
        theme: {
            background: '#1e1e1e',
            foreground: '#cccccc'
        },
        fontSize: 14,
        fontFamily: 'Consolas, monospace'
    });

    termFitAddon = new FitAddon.FitAddon();
    term.loadAddon(termFitAddon);

    term.open(document.getElementById('terminal-container'));
    termFitAddon.fit();

    term.writeln('Welcome to MrPrompt Terminal');
    term.write(`${currentTerminalPath}> `);

    terminalInitialized = true;

    // Handle Input
    term.onData(e => {
        switch (e) {
            case '\r': // Enter
                term.write('\r\n');
                executeTerminalCommand();
                break;
            case '\u007F': // Backspace (DEL)
                if (commandBuffer.length > 0) {
                    term.write('\b \b');
                    commandBuffer = commandBuffer.slice(0, -1);
                }
                break;
            default: // Print all other characters
                if (e >= String.fromCharCode(0x20) && e <= String.fromCharCode(0x7E) || e >= '\u00a0') {
                    commandBuffer += e;
                    term.write(e);
                }
        }
    });

    // Resize listener
    window.addEventListener('resize', () => {
        termFitAddon.fit();
    });
}

function executeTerminalCommand() {
    const cmd = commandBuffer.trim();
    commandBuffer = '';

    if (!cmd) {
        term.write(`${currentTerminalPath}> `);
        return;
    }

    if (cmd.toLowerCase() === 'cls' || cmd.toLowerCase() === 'clear') {
        term.clear();
        term.write(`${currentTerminalPath}> `);
        return;
    }

    // Special handling for CD
    let isCdCommand = cmd.toLowerCase().startsWith('cd ');
    let fullCmd = `cd /d "${currentTerminalPath}" && ${cmd}`;

    if (isCdCommand) {
        // Append " && cd" to output the new directory if successful
        fullCmd += " && cd";
    }

    const formData = new FormData();
    formData.append('action', 'run_cmd');
    formData.append('cmd', fullCmd);

    fetch('../../mrprompt_server/system_handler.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'ok') {
                // If it was a CD command, the last line of output should be the new path (if success)
                if (isCdCommand) {
                    if (data.code === 0) {
                        const lines = data.output.trim().split('\r\n');
                        // The last non-empty line should be the path.
                        // Sometimes output has multiple lines.
                        // We take the last one.
                        let newPath = lines[lines.length - 1].trim();
                        if (newPath) {
                            currentTerminalPath = newPath;
                        }
                        // We don't print output for CD if it's just the path, unless we want to?
                        // Standard cmd doesn't print path on CD.
                        // But "cd" command DOES print path.
                        // Our "fullCmd" ends with "&& cd", so it prints path.
                        // Let's NOT print it to term, just update prompt.
                    } else {
                        // Failed
                        term.writeln(data.output);
                    }
                } else {
                    if (data.output) {
                        const lines = data.output.split('\n');
                        lines.forEach(line => {
                            term.writeln(line.trimEnd());
                        });
                    }
                }

                term.write(`${currentTerminalPath}> `);
            } else {
                term.writeln('Error: ' + data.msg);
                term.write(`${currentTerminalPath}> `);
            }
        })
        .catch(err => {
            term.writeln('Network Error: ' + err);
            term.write(`${currentTerminalPath}> `);
        });
}

function updateTerminalPath() {
    // Run 'cd' in the current context (which is lost) 
    // Wait, PHP shell_exec context is lost immediately.
    // So "cd newpath" does NOTHING for the NEXT command unless we store it.

    // Logic fix:
    // If the command is "cd path", we need to update `currentTerminalPath` if it succeeds.
    // But since the shell closes, the "cd" only affects that single execution.
    // SO: To support "stateful" terminal, we MUST update our `currentTerminalPath` variable.
    // How to know if the path is valid?
    // We can run `cd /d "current" && cd "new" && cd`

    // We need to re-parse the last command from logic above.
    // Actually, let's just do this in the main execution flow.
}


function showAbout() {
    showModal('O Aplikacji', `
        <div style="text-align: center; line-height:1.7;">
            <h3 style="margin:0 0 4px">MrPrompt Edytor</h3>
            <p style="margin:0 0 10px; color:#999;">Wersja 4.0 &middot; część MrPrompt Server 4.0</p>
            <p style="margin:0 0 10px;">Edytor do kontrolowania kodu pisanego przez AI: przegląd zmian, cofanie, kopie, paczki plików dla czatu.</p>
            <p style="margin:0 0 10px;"><i class="fa-solid fa-code"></i> Powered by Monaco Editor</p>
            <p style="margin:0;">
                <a href="https://mrprompt.eu/" target="_blank" rel="noopener" style="color:#d18f0a">mrprompt.eu</a> &middot;
                <a href="https://mrprompt24.github.io/" target="_blank" rel="noopener" style="color:#d18f0a">mrprompt24.github.io</a> &middot;
                <a href="https://github.com/MrPrompt24" target="_blank" rel="noopener" style="color:#d18f0a">GitHub</a>
            </p>
            <p style="margin:12px 0 0; color:#888; font-size:12px;">Centrum Otwartych Innowacji</p>
        </div>
    `);
}

// nazwa użyta w menu górnym (wcześniej brakowało tej funkcji, więc przycisk nie działał)
function openAboutModal() {
    showAbout();
}

function showShortcuts() {
    showModal('Skróty klawiszowe', `
        <table style="width:100%; text-align:left; border-collapse:separate; border-spacing:0 4px;">
            <tr><td><b>F1</b></td><td>Pomoc (pełna instrukcja)</td></tr>
            <tr><td><b>Ctrl+S</b></td><td>Zapisz plik</td></tr>
            <tr><td><b>Ctrl+O</b></td><td>Otwórz plik z dysku</td></tr>
            <tr><td><b>Ctrl+Shift+G</b></td><td>Przegląd zmian (co zmieniło AI)</td></tr>
            <tr><td><b>Ctrl+Shift+F</b></td><td>Szukaj w plikach projektu</td></tr>
            <tr><td><b>Ctrl+F</b></td><td>Szukaj w bieżącym pliku</td></tr>
            <tr><td><b>Ctrl+H</b></td><td>Znajdź i zamień w bieżącym pliku</td></tr>
            <tr><td><b>Ctrl+Z / Ctrl+Y</b></td><td>Cofnij / Ponów</td></tr>
            <tr><td><b>Esc</b></td><td>Zamknij okno (np. Przegląd zmian)</td></tr>
        </table>
        <p style="margin:10px 0 0; color:#888; font-size:12px;">Nowy plik i zamknięcie karty: użyj menu Plik (przeglądarka rezerwuje Ctrl+N i Ctrl+W).</p>
    `);
}

// --- FOLDER BROWSER ---

let browserCurrentPath = window.MRP_DOCROOT || 'C:/xampp/htdocs'; // Default

function triggerOpenFolder() {
    // Initial load
    openBrowserModal(browserCurrentPath);
}

function openBrowserModal(path) {
    browserCurrentPath = path;

    const content = `
        <div id="browser-container" style="height: 300px; display: flex; flex-direction: column;">
            <div style="display: flex; gap: 5px; margin-bottom: 10px;">
                <input type="text" id="browser-path" class="modal-input" style="margin:0; flex:1;" value="${path}" onchange="navigateBrowser(this.value)">
                <button class="btn btn-secondary" onclick="navigateBrowserParent()"><i class="fa-solid fa-arrow-up"></i></button>
            </div>
            <div id="browser-list" style="flex: 1; overflow-y: auto; border: 1px solid #3e3e42; background: #1e1e1e;">
                <div style="padding:10px; color:#aaa;">Ładowanie...</div>
            </div>
        </div>
    `;

    const btns = [
        { text: 'Anuluj', class: 'btn-secondary', onclick: 'closeModal()' },
        { text: 'Wybierz ten folder', class: 'btn-primary', onclick: 'selectBrowserFolder()' }
    ];

    showModal('Wybierz Folder', content, btns);

    // Load content
    fetchDirectoryList(path);
}

function navigateBrowser(path) {
    browserCurrentPath = path; // normalize?
    document.getElementById('browser-path').value = path;
    fetchDirectoryList(path);
}

function navigateBrowserParent() {
    if (browserCurrentPath === '') return;
    let parts = browserCurrentPath.replace(/\\/g, '/').replace(/\/$/, '').split('/');
    if (parts.length > 1) {
        parts.pop();
        let newPath = parts.join('/') + '/';
        navigateBrowser(newPath);
    } else {
        navigateBrowser('');
    }
}

function fetchDirectoryList(path) {
    const listContainer = document.getElementById('browser-list');
    if (!listContainer) return;

    const formData = new FormData();
    formData.append('action', 'list_dir');
    formData.append('path', path);

    fetch(fileHandlerUrl, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'ok') {
                listContainer.innerHTML = '';
                // Folders only for browsing usually, but let's show all
                // Sort: folders first, then files
                const items = data.files.sort((a, b) => {
                    if (a.is_dir === b.is_dir) return a.name.localeCompare(b.name);
                    return a.is_dir ? -1 : 1;
                });

                if (items.length === 0) {
                    listContainer.innerHTML = '<div style="padding:10px; color:#777;">(Pusty folder)</div>';
                    return;
                }

                items.forEach(item => {
                    const div = document.createElement('div');
                    div.className = 'browser-item';

                    // Determine icon and color
                    let iconHtml = getIcon(item.name, item.is_dir);
                    let colorStyle = item.is_dir ? 'color:#e0ca2c;' : `color:${getIconColor(item.name)};`;

                    div.innerHTML = `<span style="margin-right:8px; ${colorStyle}">${iconHtml}</span> ${item.name}`;

                    if (item.is_dir) {
                        div.style.cursor = 'pointer';
                        div.onclick = () => navigateBrowser(item.path);
                    } else {
                        div.style.cursor = 'default';
                        div.style.opacity = '0.6'; // Dim files to show they are not selectable as folder
                    }

                    listContainer.appendChild(div);
                });
            } else {
                listContainer.innerHTML = `<div style="padding:10px; color:red;">Błąd: ${data.msg}</div>`;
            }
        })
        .catch(err => {
            if (listContainer) listContainer.innerHTML = `<div style="padding:10px; color:red;">Błąd sieci</div>`;
        });
}

function selectBrowserFolder() {
    loadSidebar(browserCurrentPath);
    closeModal();
}

// --- VIEW ACTIONS ---

function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    if (sidebar.style.display === 'none') {
        sidebar.style.display = 'flex';
    } else {
        sidebar.style.display = 'none';
    }
    editor.layout(); // Refresh editor size
}

function toggleWordWrap() {
    const current = editor.getOption(monaco.editor.EditorOption.wordWrap);
    const newState = current === 'on' ? 'off' : 'on';
    editor.updateOptions({ wordWrap: newState });
}

// --- FILE EXPLORER ---

let currentSidebarPath = '';

function loadSidebar(path) {
    currentSidebarPath = path;
    const sidebar = document.querySelector('.file-explorer');
    sidebar.innerHTML = ''; // Clear
    const rootItem = document.createElement('div');
    rootItem.style.padding = '5px 10px';
    rootItem.style.color = '#ccc';
    rootItem.style.cursor = 'pointer';
    rootItem.innerText = path;
    rootItem.oncontextmenu = (e) => {
        e.preventDefault();
        e.stopPropagation();
        showContextMenu(e.clientX, e.clientY, path, 'folder');
    };
    sidebar.appendChild(rootItem);

    const container = document.createElement('div');
    sidebar.appendChild(container);

    loadDirectory(path, container);
}

function refreshSidebar() {
    if (currentSidebarPath) {
        loadSidebar(currentSidebarPath);
    }
}

// --- MODAL SYSTEM ---

function showModal(title, content, buttons = null) {
    document.getElementById('modal-title').innerText = title;
    document.getElementById('modal-body').innerHTML = content;

    const footer = document.getElementById('modal-footer');
    footer.innerHTML = '';

    if (buttons) {
        buttons.forEach(btn => {
            const button = document.createElement('button');
            button.className = `btn ${btn.class || 'btn-secondary'}`;
            button.innerText = btn.text;
            button.setAttribute('onclick', btn.onclick);
            footer.appendChild(button);
        });
    } else {
        // Default OK button
        footer.innerHTML = '<button class="btn btn-primary" onclick="closeModal()">OK</button>';
    }

    document.getElementById('app-modal').classList.add('show');
}

function closeModal() {
    document.getElementById('app-modal').classList.remove('show');
}


// --- FOOTER STATS ---

function startFooterUpdates() {
    updateFooterStats();
    // Update every 10 seconds
    setInterval(updateFooterStats, 10000);

    // Listen for model changes for linter
    if (editor) {
        editor.onDidChangeModelDecorations(() => {
            updateLinterStats();
        });
    }
}


// --- GLOBAL SEARCH ---

function openSearchModal() {
    document.getElementById('search-modal').classList.add('show');
    document.getElementById('search-input').focus();
    // Pre-fill with selected text if any
    const selection = editor.getModel().getValueInRange(editor.getSelection());
    if (selection && selection.length < 50) {
        document.getElementById('search-input').value = selection;
    }
}

function closeSearchModal() {
    document.getElementById('search-modal').classList.remove('show');
}

function handleSearchKeyPress(e) {
    if (e.key === 'Enter') {
        performSearch();
    }
}

function performSearch() {
    const query = document.getElementById('search-input').value;
    const resultsContainer = document.getElementById('search-results');

    if (!query) return;

    resultsContainer.innerHTML = '<div style="padding:10px; color:#aaa;">Szukanie...</div>';

    // We search in current Sidebar Path or default
    const searchPath = currentSidebarPath || 'C:/xampp/htdocs/mrprompt_server/edytor/szablon';

    const formData = new FormData();
    formData.append('action', 'search');
    formData.append('path', searchPath);
    formData.append('query', query);

    fetch(fileHandlerUrl, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'ok') {
                displaySearchResults(data.results);
            } else {
                resultsContainer.innerHTML = `<div style="padding:10px; color:red;">Błąd: ${data.msg}</div>`;
            }
        })
        .catch(err => {
            resultsContainer.innerHTML = `<div style="padding:10px; color:red;">Błąd sieci</div>`;
        });
}

function displaySearchResults(results) {
    const container = document.getElementById('search-results');
    container.innerHTML = '';

    if (results.length === 0) {
        container.innerHTML = '<div style="padding:10px; color:#ccc;">Brak wyników.</div>';
        return;
    }

    results.forEach(res => {
        const div = document.createElement('div');
        div.className = 'search-result-item';
        div.onclick = () => {
            // Open file and go to line
            openFileFromSidebar(res.file, null, res.line);
            closeSearchModal();
        };

        let displayPath = res.file.replace(/\\/g, '/');
        // shorten path visually
        if (currentSidebarPath) {
            displayPath = displayPath.replace(currentSidebarPath.replace(/\\/g, '/'), '');
        }

        div.innerHTML = `
            <div class="search-result-file">${displayPath}</div>
            <div class="search-result-line">${res.line}: ${res.content.replace(/</g, '&lt;')}</div>
        `;
        container.appendChild(div);
    });
}

function updateFooterStats() {
    updateGitBranch();
    updateLinterStats();
}


// --- LIVE PREVIEW ---

let isPreviewOpen = false;

function toggleLivePreview() {
    const preview = document.getElementById('preview-container');
    const editorEl = document.getElementById('monaco-editor');

    if (preview.style.display === 'none') {
        preview.style.display = 'block';
        editorEl.style.width = '50%'; // Simple split
        isPreviewOpen = true;
        updatePreview();
    } else {
        preview.style.display = 'none';
        editorEl.style.width = '100%'; // Full width
        isPreviewOpen = false;
    }
    editor.layout();
}

function updatePreview() {
    if (!isPreviewOpen) return;

    const tab = tabs.find(t => t.id === activeTabId);
    if (!tab) return;

    const frame = document.getElementById('preview-frame');
    const content = editor.getValue();

    if (tab.name.endsWith('.html')) {
        // Direct inject
        frame.srcdoc = content;
    } else if (tab.name.endsWith('.php')) {
        // Must be saved
        if (tab.modified) {
            // Maybe auto-save or warn?
            // For now, let's just use the file URL if it exists
        }

        if (tab.path) {
            // Convert C:/xampp/htdocs/... to http://localhost/...
            // Path normalization
            let path = tab.path.replace(/\\/g, '/');
            if (path.indexOf('htdocs/') !== -1) {
                let relPath = path.split('htdocs/')[1];
                frame.srcdoc = ''; // clear doc
                frame.src = 'http://localhost/' + relPath + (relPath.indexOf('?') === -1 ? '?' : '&') + '_=' + Date.now();
            } else {
                frame.srcdoc = "Cannot preview PHP file outside htdocs.";
                frame.removeAttribute('src');
            }
        }
    } else {
        frame.srcdoc = "<p>Podgląd niedostępny dla tego typu pliku.</p>";
        frame.removeAttribute('src');
    }
}


function updateGitBranch() {
    // We assume the CWD for git is the sidebar root, or we check specific path
    // For now let's try to check the root of sidebar
    const sidebarRoot = document.querySelector('.file-explorer > div:first-child')?.innerText;
    if (!sidebarRoot) return;

    // We can try to run git branch in that folder. 
    // We need to pass cwd or chain commands: cd path && git branch --show-current
    // Note: Windows uses & or &&.

    const cmd = `cd /d "${sidebarRoot}" && git branch --show-current`;

    const formData = new FormData();
    formData.append('action', 'run_cmd');
    formData.append('cmd', cmd);

    fetch('../../mrprompt_server/system_handler.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            const footerBranch = document.querySelector('.footer-left .footer-item:nth-child(1)');
            if (data.status === 'ok' && data.output.trim()) {
                footerBranch.innerHTML = `<span class="icon"><i class="fa-solid fa-code-branch"></i></span> ${data.output.trim()}`;
            } else {
                // Maybe not a git repo
                footerBranch.innerHTML = `<span class="icon"><i class="fa-solid fa-code-branch"></i></span> -`;
            }
        })
        .catch(() => { });
}

function updateLinterStats() {
    if (!monaco || !editor) return;
    const model = editor.getModel();
    if (!model) return;

    const markers = monaco.editor.getModelMarkers({ resource: model.uri });
    const errors = markers.filter(m => m.severity === monaco.MarkerSeverity.Error).length;
    const warnings = markers.filter(m => m.severity === monaco.MarkerSeverity.Warning).length;

    const footerErrors = document.querySelector('.footer-left .footer-item:nth-child(2)');
    const footerWarnings = document.querySelector('.footer-left .footer-item:nth-child(3)');

    if (footerErrors) footerErrors.innerHTML = `<span class="icon"><i class="fa-regular fa-circle-xmark"></i></span> ${errors}`;
    if (footerWarnings) footerWarnings.innerHTML = `<span class="icon"><i class="fa-solid fa-triangle-exclamation"></i></span> ${warnings}`;
}


// --- INTELLISENSE ---

function registerIntelliSense() {
    // Register for all languages
    monaco.languages.registerCompletionItemProvider('php', getCompletionProvider());
    monaco.languages.registerCompletionItemProvider('javascript', getCompletionProvider());
    monaco.languages.registerCompletionItemProvider('html', getCompletionProvider());
    monaco.languages.registerCompletionItemProvider('css', getCompletionProvider());
}

function getCompletionProvider() {
    return {
        provideCompletionItems: function (model, position) {
            const textUntilPosition = model.getValueInRange({
                startLineNumber: 1,
                startColumn: 1,
                endLineNumber: position.lineNumber,
                endColumn: position.column
            });

            // Basic scanner of all open tabs
            const suggestions = [];

            // 1. Scan current file keywords
            const currentContent = model.getValue();
            const words = currentContent.match(/\b[a-zA-Z_]\w*\b/g) || [];

            // 2. Scan other tabs
            tabs.forEach(t => {
                if (t.content) {
                    const tWords = t.content.match(/\b[a-zA-Z_]\w*\b/g) || [];
                    words.push(...tWords);
                }
            });

            const uniqueWords = [...new Set(words)];

            uniqueWords.forEach(word => {
                suggestions.push({
                    label: word,
                    kind: monaco.languages.CompletionItemKind.Text,
                    insertText: word
                });
            });

            return { suggestions: suggestions };
        }
    };
}


function loadDirectory(path, container) {
    const formData = new FormData();
    formData.append('action', 'list_dir');
    formData.append('path', path);

    fetch(fileHandlerUrl, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'ok') {
                container.innerHTML = ''; // Clear placeholder
                // Sort: folders first, then files
                const files = data.files.sort((a, b) => {
                    if (a.is_dir === b.is_dir) return a.name.localeCompare(b.name);
                    return a.is_dir ? -1 : 1;
                });

                files.forEach(file => {
                    const item = document.createElement('div');
                    item.className = 'file-tree-item';

                    const label = document.createElement('div');
                    label.className = `file-item ${file.is_dir ? 'folder-item' : ''}`;
                    label.innerHTML = `
                        <span class="arrow ${file.is_dir ? 'arrow-right' : ''}" style="width:10px; display:inline-block;">${file.is_dir ? '▶' : ''}</span>
                        <span class="icon">${getIcon(file.name, file.is_dir)}</span>
                        <span>${file.name}</span>
                    `;

                    item.appendChild(label);
                    container.appendChild(item);

                    if (file.is_dir) {
                        const subContainer = document.createElement('div');
                        subContainer.className = 'nested';
                        item.appendChild(subContainer);

                        const toggle = (e) => {
                            e.stopPropagation();
                            hideContextMenu();
                            const arrow = label.querySelector('.arrow');
                            if (subContainer.classList.contains('active')) {
                                subContainer.classList.remove('active');
                                arrow.innerText = '▶';
                                arrow.classList.remove('arrow-down');
                            } else {
                                subContainer.classList.add('active');
                                arrow.innerText = '▼';
                                arrow.classList.add('arrow-down');
                                if (subContainer.innerHTML === '') {
                                    loadDirectory(file.path, subContainer);
                                }
                            }
                        };
                        label.onclick = toggle;

                        // Right Click
                        label.oncontextmenu = (e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            showContextMenu(e.clientX, e.clientY, file.path, 'folder');
                        };

                    } else {
                        label.onclick = () => {
                            hideContextMenu();
                            openFileFromSidebar(file.path, file.name);
                        };
                        // Right Click
                        label.oncontextmenu = (e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            showContextMenu(e.clientX, e.clientY, file.path, 'file');
                        };
                    }
                });
            } else {
                container.innerText = "Błąd: " + data.msg;
            }
        })
        .catch(err => {
            console.error(err);
            container.innerText = "Błąd połączenia";
        });
}

function openFileFromSidebar(path, name) {
    // Check if tab exists
    const existing = tabs.find(t => t.path === path);
    if (existing) {
        activateTab(existing.id);
        return;
    }

    // Load file content
    const formData = new FormData();
    formData.append('action', 'open');
    formData.append('path', path);

    fetch(fileHandlerUrl, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'ok') {
                createNewTab(name, data.content);
                // Update the new tab with path
                const newTab = tabs[tabs.length - 1];
                newTab.path = data.path;
                // puste, nietknięte "Untitled" z początku sesji nie jest już potrzebne
                const first = tabs[0];
                if (first && first !== newTab && !first.path && !first.modified && !first.content) {
                    tabs.splice(0, 1);
                    renderTabs();
                }
            } else {
                showModal('Błąd', "Nie można otworzyć pliku: " + data.msg);
            }
        });
}

// --- BACKUP SYSTEM ---

function getBackupPath() {
    return localStorage.getItem('mrprompt_backup_path') || '';
}

function setBackupPath(path) {
    localStorage.setItem('mrprompt_backup_path', path);
}

function openBackupSettings() {
    const current = getBackupPath();
    const content = `
        <div>
            <p>Wskaż folder, w którym mają być zapisywane kopie:</p>
            <div style="display: flex; gap: 5px;">
                <input type="text" id="backup-path-input" class="modal-input" value="${current}" placeholder="np. c:/xampp/htdocs/backups">
                <button class="btn btn-secondary" onclick="browseForBackupPath()"><i class="fa-solid fa-folder-open"></i></button>
            </div>
        </div>
    `;

    const btns = [
        { text: 'Anuluj', class: 'btn-secondary', onclick: 'closeModal()' },
        { text: 'Zapisz', class: 'btn-primary', onclick: 'saveBackupSettings()' }
    ];

    showModal('Ustawienia Kopii', content, btns);
}

function saveBackupSettings() {
    const path = document.getElementById('backup-path-input').value;
    if (path) {
        setBackupPath(path);
        closeModal();
        showModal('Sukces', 'Zapisano ścieżkę kopii.');
    } else {
        showModal('Błąd', 'Ścieżka nie może być pusta.');
    }
}

function browseForBackupPath() {
    const current = document.getElementById('backup-path-input').value || 'c:/';

    // Save original select function
    const originalSelect = window.selectBrowserFolder;

    // Override select function for this context
    window.selectBrowserFolder = function () {
        // This is called when user clicks "Wybierz ten folder" in the browser modal
        const selectedPath = browserCurrentPath;

        // Restore original function immediately
        window.selectBrowserFolder = originalSelect;

        // Close the browser modal
        closeModal();

        // Re-open settings modal with the selected path
        // We need a small delay to ensure modal close/open transition works if needed, 
        // but since we replace content it might be fine. 
        // Safer to just call openBackupSettings again which replaces modal content.
        setTimeout(() => {
            openBackupSettings();
            // Pre-fill
            document.getElementById('backup-path-input').value = selectedPath;
        }, 100);
    };

    // Hook into closeModal to restore original if user cancels
    const originalClose = window.closeModal;
    window.closeModal = function () {
        window.selectBrowserFolder = originalSelect;
        window.closeModal = originalClose; // Restore self
        originalClose(); // Call original

        // Re-open settings if we just cancelled browsing? 
        // User might want to go back to settings.
        setTimeout(() => {
            openBackupSettings();
            document.getElementById('backup-path-input').value = current;
        }, 100);
    };

    // Open the browser modal
    openBrowserModal(current);
}

function openBackupBrowser() {
    const path = getBackupPath();
    if (!path) {
        showModal('Błąd', 'Nie ustawiono folderu kopii. Przejdź do Kopie > Ustawienia.');
        return;
    }

    // Check if valid first?
    const content = `
        <div id="backup-browser-container" style="height: 500px; display: flex; flex-direction: column;">
            <div style="padding: 10px; background: #252526; border-bottom: 1px solid #3e3e42;">
                Lokalizacja: <b>${path}</b>
                <button class="btn btn-secondary" style="float:right;" onclick="refreshBackupBrowser('${path.replace(/\\/g, '\\\\')}')"><i class="fa-solid fa-rotate"></i></button>
            </div>
            <div id="backup-browser-list" style="flex: 1; overflow-y: auto; border: 1px solid #3e3e42; background: #1e1e1e;">
                <div style="padding:10px; color:#aaa;">Ładowanie...</div>
            </div>
        </div>
    `;

    const btns = [
        { text: 'Zamknij', class: 'btn-secondary', onclick: 'closeModal()' }
    ];

    // Large modal style hack
    const modalStyle = document.querySelector('.modal').style;
    const oldWidth = modalStyle.width;
    modalStyle.width = '800px';

    showModal('Przeglądaj Kopie', content, btns);

    // Restore width on close
    const originalClose = window.closeModal;
    window.closeModal = function () {
        document.querySelector('.modal').style.width = ''; // Reset
        document.getElementById('app-modal').classList.remove('show');
        window.closeModal = originalClose;
    };

    fetchBackupList(path);
}

function fetchBackupList(path) {
    const listContainer = document.getElementById('backup-browser-list');

    const formData = new FormData();
    formData.append('action', 'list_dir');
    formData.append('path', path);

    fetch(fileHandlerUrl, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'ok') {
                listContainer.innerHTML = '';
                // Sort: newest first (assuming naming YYYY-MM-DD...)
                const items = data.files.sort((a, b) => b.name.localeCompare(a.name));

                items.forEach(item => {
                    const div = document.createElement('div');
                    div.className = 'browser-item';
                    div.style.justifyContent = 'space-between'; // Align delete icon right
                    div.style.display = 'flex';

                    let iconHtml = getIcon(item.name, item.is_dir);

                    // Main content (icon + name)
                    const leftContent = document.createElement('div');
                    leftContent.style.display = 'flex';
                    leftContent.style.alignItems = 'center';
                    leftContent.innerHTML = `<span style="margin-right:8px; color:${item.is_dir ? '#e0ca2c' : '#ccc'}">${iconHtml}</span> ${item.name}`;

                    div.appendChild(leftContent);

                    // Delete Button
                    const deleteBtn = document.createElement('div');
                    deleteBtn.innerHTML = '<i class="fa-solid fa-trash"></i>';
                    deleteBtn.style.color = '#777';
                    deleteBtn.style.cursor = 'pointer';
                    deleteBtn.style.fontSize = '12px';
                    deleteBtn.title = 'Usuń kopię';

                    deleteBtn.onmouseover = () => deleteBtn.style.color = '#cc0000';
                    deleteBtn.onmouseout = () => deleteBtn.style.color = '#777';
                    deleteBtn.onclick = (e) => {
                        e.stopPropagation();
                        triggerDeleteBackup(item.path);
                    };

                    div.appendChild(deleteBtn);

                    listContainer.appendChild(div);
                });
            } else {
                listContainer.innerHTML = `<div style="padding:10px; color:red;">Błąd: ${data.msg}</div>`;
            }
        });
}

function triggerDeleteBackup(path) {
    const name = path.split(/[/\\]/).pop();

    // Custom Modal for Backup Deletion
    const btns = [
        { text: 'Anuluj', class: 'btn-secondary', onclick: 'closeModal()' },
        { text: 'Usuń', class: 'btn-primary', onclick: `confirmDeleteBackup('${path.replace(/\\/g, '\\\\')}')` }
    ];

    showModal('Potwierdzenie',
        `<div style="text-align:center;">
             <i class="fa-solid fa-triangle-exclamation" style="color:#cc0000; font-size: 30px; margin-bottom:10px;"></i>
             <p>Czy na pewno chcesz trwale usunąć kopię:</p>
             <p><b>${name}</b>?</p>
             <p style="color:#cc0000; font-size:12px;">Tej operacji nie można cofnąć.</p>
        </div>`,
        btns
    );
}

function confirmDeleteBackup(path) {
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('path', path);

    fetch(fileHandlerUrl, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'ok') {
                closeModal(); // Close confirm
                showModal('Sukces', 'Kopia została usunięta.');
                refreshBackupBrowser(getBackupPath()); // Refresh list
            } else {
                showModal('Błąd', 'Nie udało się usunąć: ' + data.msg);
            }
        })
        .catch(err => showModal('Błąd', 'Błąd połączenia: ' + err));
}

function refreshBackupBrowser(path) {
    fetchBackupList(path);
}

function triggerMakeCopy() {
    hideContextMenu();
    if (!contextMenuTarget || contextMenuTarget.type !== 'folder') return;

    const dest = getBackupPath();
    if (!dest) {
        showModal('Błąd', 'Nie skonfigurowano folderu kopii. Przejdź do Kopie > Ustawienia.');
        return;
    }

    const { path } = contextMenuTarget; // Source folder

    // Confirm
    const name = path.split(/[/\\]/).pop();
    const btns = [
        { text: 'Anuluj', class: 'btn-secondary', onclick: 'closeModal()' },
        { text: 'Zrób Kopię', class: 'btn-primary', onclick: `confirmMakeCopy('${path.replace(/\\/g, '\\\\')}')` }
    ];

    showModal('Potwierdzenie', `Czy na pewno chcesz zrobić kopię folderu <b>${name}</b>?<br>Kopia zostanie zapisana w: ${dest}`, btns);
}

function confirmMakeCopy(sourcePath) {
    const destRoot = getBackupPath();

    const formData = new FormData();
    formData.append('action', 'copy_dir');
    formData.append('path', sourcePath);
    formData.append('dest_root', destRoot);

    showModal('Kopiowanie...', 'Trwa tworzenie kopii...', []); // Loading state

    fetch(fileHandlerUrl, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'ok') {
                showModal('Sukces', `Kopia została utworzona:<br>${data.dest}`);
            } else {
                showModal('Błąd', 'Nie udało się utworzyć kopii: ' + data.msg);
            }
        })
        .catch(err => showModal('Błąd', 'Błąd: ' + err));
}

// --- CONTEXT MENU ---

let contextMenuTarget = null;

function showContextMenu(x, y, path, type) {
    contextMenuTarget = { path, type };
    const menu = document.getElementById('context-menu');

    // Inject items
    let menuContent = '';

    // Nowy plik / folder (w folderze albo obok pliku)
    menuContent += `<div class="dropdown-item" onclick="edNewFromCtx('file')"><i class="fa-solid fa-file-circle-plus" style="margin-right:8px; color:#73c991;"></i> Nowy plik…</div>`;
    menuContent += `<div class="dropdown-item" onclick="edNewFromCtx('folder')"><i class="fa-solid fa-folder-plus" style="margin-right:8px; color:#e2c08d;"></i> Nowy folder…</div>`;
    menuContent += `<div class="dropdown-divider"></div>`;

    // Rename (Common)
    menuContent += `<div class="dropdown-item" onclick="triggerRenameItem()"><i class="fa-solid fa-pen-to-square" style="margin-right:8px; color:#3b82f6;"></i> Zmień nazwę</div>`;

    // Duplicate (Folder/File)
    menuContent += `<div class="dropdown-item" onclick="triggerMakeCopy()"><i class="fa-solid fa-copy" style="margin-right:8px; color:#4caf50;"></i> Wykonaj Kopię</div>`;

    // Download (File only)
    if (type === 'file') {
        menuContent += `<div class="dropdown-item" onclick="triggerDownloadItem()"><i class="fa-solid fa-download" style="margin-right:8px; color:#e0ca2c;"></i> Pobierz</div>`;
    }

    // Delete (Common)
    menuContent += `<div class="dropdown-divider"></div>`;
    menuContent += `<div class="dropdown-item" onclick="triggerDeleteItem()"><i class="fa-solid fa-trash" style="margin-right:8px; color:#cc0000;"></i> Usuń</div>`;

    menu.innerHTML = menuContent;

    menu.style.display = 'block';
    menu.style.left = x + 'px';
    menu.style.top = y + 'px';
}

function hideContextMenu() {
    const menu = document.getElementById('context-menu');
    menu.style.display = 'none';
}


// Global click to hide
document.addEventListener('click', (e) => {
    if (!e.target.closest('#context-menu')) {
        hideContextMenu();
    }
});

function triggerDeleteItem() {
    hideContextMenu();
    if (!contextMenuTarget) return;

    const { path, type } = contextMenuTarget;
    const name = path.split(/[/\\]/).pop();

    const btns = [
        { text: 'Anuluj', class: 'btn-secondary', onclick: 'closeModal()' },
        { text: 'Usuń', class: 'btn-primary', onclick: 'confirmDeleteItem()' } // Should maybe style red?
    ];

    // Add custom style for red button via inline for now or class if we had one
    // Let's stick to standard but maybe add warning icon

    showModal('Potwierdzenie',
        `<div style="text-align:center;">
            <i class="fa-solid fa-triangle-exclamation" style="color:#cc0000; font-size: 30px; margin-bottom:10px;"></i>
            <p>Czy na pewno chcesz usunąć ${type === 'folder' ? 'folder' : 'plik'}:</p>
            <p><b>${name}</b>?</p>
            ${type === 'folder' ? '<p style="color:#cc0000; font-size:12px;">UWAGA: Wszystkie pliki wewnątrz zostaną usunięte!</p>' : ''}
        </div>`,
        btns.map(b => b.text === 'Usuń' ? { ...b, class: 'btn-primary', onclick: `confirmDeleteItem('${path.replace(/\\/g, '\\\\')}')` } : b)
        // passing path safely is tricky in string template, simpler to rely on contextMenuTarget global if we confirm immediately
        // BUT modal is async-ish UI. Ideally pass nothing and use gloabl contextMenuTarget
    );

    // Override button onclick to use global target
    // Actually our showModal generates string onclicks. 
    // Let's redesign confirmDeleteItem to read global contextMenuTarget
}

function confirmDeleteItem() {
    if (!contextMenuTarget) return;

    const { path } = contextMenuTarget;

    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('path', path);

    fetch(fileHandlerUrl, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'ok') {
                closeModal();
                showModal('Sukces', 'Usunięto pomyślnie!');
                refreshSidebar();

                // Close tab if open
                // normalize path to match tab path if needed
                const tab = tabs.find(t => t.path && t.path.replace(/\\/g, '/') === path.replace(/\\/g, '/'));
                if (tab) {
                    closeTab(tab.id);
                }
            } else {
                closeModal(); // Close confirmation
                showModal('Błąd', 'Nie udalo się usunąć: ' + data.msg);
            }
        })
        .catch(err => {
            closeModal();
            showModal('Błąd', 'Błąd połączenia: ' + err);
        });
}

// Override getIcon to handle folders
const originalGetIcon = getIcon;
getIcon = function (name, isDir) {
    if (isDir) return '<i class="fa-solid fa-folder"></i>';
    return originalGetIcon(name);
};


function triggerRenameItem() {
    hideContextMenu();
    if (!contextMenuTarget) return;

    const { path } = contextMenuTarget;
    const oldName = path.split(/[/\\]/).pop();

    const content = `
        <div>
            <label>Nowa nazwa:</label>
            <input type="text" id="rename-input" class="modal-input" value="${oldName}">
        </div>
    `;

    const btns = [
        { text: 'Anuluj', class: 'btn-secondary', onclick: 'closeModal()' },
        { text: 'Zmień', class: 'btn-primary', onclick: `confirmRenameItem('${path.replace(/\\/g, '\\\\')}')` }
    ];

    showModal('Zmień Nazwę', content, btns);
    // Focus input
    setTimeout(() => document.getElementById('rename-input').select(), 100);
}

function confirmRenameItem(oldPath) {
    const newName = document.getElementById('rename-input').value;
    if (!newName) return;

    // Construct new path
    // Need parent dir of oldPath
    // We can assume format c:/.../...
    let parent = oldPath.replace(/[/\\][^\/\\]*$/, '');
    // verify if root?
    if (parent === oldPath) {
        // Should not happen unless root drive
        showModal('Błąd', 'Nie można zmienić nazwy dysku.');
        return;
    }

    // Normalize parent to end with separator?? 
    // Usually replace behaves ok.

    let newPath = parent + '/' + newName;

    const formData = new FormData();
    formData.append('action', 'rename');
    formData.append('path', oldPath);
    formData.append('new_path', newPath);

    fetch(fileHandlerUrl, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'ok') {
                closeModal();
                refreshSidebar();

                // Update if open locally
                const tab = tabs.find(t => t.path && t.path.replace(/\\/g, '/') === oldPath.replace(/\\/g, '/'));
                if (tab) {
                    tab.path = newPath;
                    tab.name = newName;
                    renderTabs();
                }
            } else {
                showModal('Błąd', 'Błąd zmiany nazwy: ' + data.msg);
            }
        })
        .catch(err => showModal('Błąd', 'Błąd: ' + err));
}

function triggerDownloadItem() {
    hideContextMenu();
    if (!contextMenuTarget || contextMenuTarget.type !== 'file') return;

    const { path } = contextMenuTarget;

    // Create invisible form to POST logic or just window.open if GET supported.
    // Our handler supports POST.
    // Standard window.open is GET.
    // We can create a temporary form.

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = fileHandlerUrl;
    form.target = '_blank';

    const inputAction = document.createElement('input');
    inputAction.type = 'hidden';
    inputAction.name = 'action';
    inputAction.value = 'download';
    form.appendChild(inputAction);

    const inputPath = document.createElement('input');
    inputPath.type = 'hidden';
    inputPath.name = 'path';
    inputPath.value = path;
    form.appendChild(inputPath);

    const inputToken = document.createElement('input');
    inputToken.type = 'hidden';
    inputToken.name = '_token';
    inputToken.value = window.MRP_TOKEN;
    form.appendChild(inputToken);

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}


