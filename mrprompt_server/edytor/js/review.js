// Nadzór nad kodem pisanym przez AI: przegląd zmian (diff), auto-odświeżanie kart, paczka kontekstu,
// szybkie polecenia i pliki sterujące. Ładowany po app.js (korzysta z jego zmiennych globalnych).
(function () {
    'use strict';

    const HANDLER = '../review_handler.php';
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const norm = (p) => String(p || '').replace(/\\/g, '/');

    function post(action, data = {}) {
        const fd = new FormData();
        fd.append('action', action);
        for (const [k, v] of Object.entries(data)) {
            if (v !== undefined && v !== null) fd.append(k, typeof v === 'object' ? JSON.stringify(v) : v);
        }
        return fetch(HANDLER, { method: 'POST', body: fd })
            .then((r) => r.json())
            .catch(() => ({ status: 'error', msg: 'Błąd połączenia z serwerem.' }));
    }

    function toast(msg, type = 'info') {
        let c = document.getElementById('rv-toasts');
        if (!c) {
            c = document.createElement('div');
            c.id = 'rv-toasts';
            document.body.appendChild(c);
        }
        const t = document.createElement('div');
        t.className = 'rv-toast ' + type;
        t.textContent = msg;
        c.appendChild(t);
        setTimeout(() => { t.style.opacity = '0'; t.style.transition = 'opacity .3s'; setTimeout(() => t.remove(), 300); }, 4200);
    }

    function projectPath() {
        if (typeof currentSidebarPath !== 'undefined' && currentSidebarPath) return currentSidebarPath;
        const t = tabs.find((x) => x.id === activeTabId);
        if (t && t.path) return t.path.replace(/[\/\\][^\/\\]*$/, '');
        return window.MRP_DOCROOT || '';
    }

    function langFor(name) {
        const ext = (String(name).split('.').pop() || '').toLowerCase();
        return ({
            php: 'php', js: 'javascript', mjs: 'javascript', ts: 'typescript', jsx: 'javascript', tsx: 'typescript',
            css: 'css', scss: 'scss', less: 'less', html: 'html', htm: 'html', json: 'json', md: 'markdown',
            py: 'python', sql: 'sql', xml: 'xml', yml: 'yaml', yaml: 'yaml', ini: 'ini', sh: 'shell', bat: 'bat',
        })[ext] || 'plaintext';
    }

    /* =========================================================
     *  1. PRZEGLĄD ZMIAN (git / kopia zapasowa)
     * ========================================================= */
    const rv = { side: true, built: false, mode: 'git', repo: null, files: [], sel: null, snaps: [], diff: null, models: [], busy: false, base: '' };

    function buildPanel() {
        if (rv.built) return;
        rv.built = true;
        const el = document.createElement('div');
        el.id = 'rv-panel';
        el.className = 'rv-overlay';
        el.innerHTML = `
        <div class="rv-window">
            <div class="rv-head">
                <div class="rv-title"><i class="fa-solid fa-code-compare"></i> Przegląd zmian <span id="rv-proj" style="color:#888;font-weight:400"></span></div>
                <select id="rv-mode" title="Względem czego porównać"></select>
                <div class="rv-spacer"></div>
                <button class="rv-btn" id="rv-refresh" title="Odśwież listę zmian"><i class="fa-solid fa-rotate"></i> Odśwież</button>
                <button class="rv-btn primary" id="rv-commit"><i class="fa-solid fa-check"></i> Zatwierdź (commit)</button>
                <button class="rv-x" id="rv-close" title="Zamknij (Esc)"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="rv-body">
                <div class="rv-list">
                    <input type="text" id="rv-filter" placeholder="Filtruj pliki…" autocomplete="off">
                    <div class="rv-files" id="rv-files"></div>
                    <div class="rv-foot"><span id="rv-stat"></span><a id="rv-all">wszystkie</a></div>
                </div>
                <div class="rv-diff">
                    <div class="rv-difftool">
                        <span class="path" id="rv-path">Wybierz plik z listy</span>
                        <button class="rv-btn" id="rv-inline" title="Przełącz widok obok siebie / w jednej kolumnie"><i class="fa-solid fa-table-columns"></i></button>
                        <button class="rv-btn" id="rv-open"><i class="fa-solid fa-pen-to-square"></i> Otwórz w edytorze</button>
                        <button class="rv-btn danger" id="rv-revert"><i class="fa-solid fa-rotate-left"></i> Cofnij zmiany</button>
                    </div>
                    <div class="rv-monaco" id="rv-monaco"></div>
                    <div class="rv-empty" id="rv-empty"></div>
                </div>
            </div>
        </div>`;
        document.body.appendChild(el);

        el.addEventListener('mousedown', (e) => { if (e.target === el) closeReview(); });
        document.getElementById('rv-close').onclick = closeReview;
        document.getElementById('rv-refresh').onclick = () => refreshReview(true);
        document.getElementById('rv-mode').onchange = (e) => { rv.mode = e.target.value; refreshReview(); };
        document.getElementById('rv-filter').oninput = renderFiles;
        document.getElementById('rv-all').onclick = toggleAllChecks;
        document.getElementById('rv-open').onclick = rvOpenInEditor;
        document.getElementById('rv-revert').onclick = rvAskRevert;
        document.getElementById('rv-commit').onclick = rvAskCommit;
        document.getElementById('rv-inline').onclick = () => {
            if (!rv.diff) return;
            rv.side = !rv.side;
            rv.diff.updateOptions({ renderSideBySide: rv.side });
        };
        document.addEventListener('keydown', (e) => {
            if (!isReviewOpen()) return;
            if (e.key === 'Escape' && !document.getElementById('app-modal').classList.contains('show')) closeReview();
            if ((e.key === 'ArrowDown' || e.key === 'ArrowUp') && document.activeElement.tagName !== 'TEXTAREA') {
                const list = visibleFiles();
                if (!list.length) return;
                const i = Math.max(0, list.findIndex((f) => rv.sel && f.file === rv.sel.file));
                const n = list[Math.min(list.length - 1, Math.max(0, i + (e.key === 'ArrowDown' ? 1 : -1)))];
                if (n) { e.preventDefault(); selectFile(n); }
            }
        });
    }

    const isReviewOpen = () => rv.built && document.getElementById('rv-panel').classList.contains('show');

    function openReview() {
        buildPanel();
        document.getElementById('rv-panel').classList.add('show');
        refreshReview(true);
    }
    function closeReview() {
        const p = document.getElementById('rv-panel');
        if (p) p.classList.remove('show');
        disposeDiff();
        refreshRepoBadge();
    }

    function disposeDiff() {
        if (rv.diff) { rv.diff.setModel(null); }
        rv.models.forEach((m) => m.dispose());
        rv.models = [];
    }

    function setEmpty(html) {
        const e = document.getElementById('rv-empty');
        const m = document.getElementById('rv-monaco');
        if (html) { e.innerHTML = html; e.style.display = 'flex'; m.style.display = 'none'; }
        else { e.style.display = 'none'; m.style.display = 'block'; }
    }

    async function refreshReview(resetSel) {
        if (rv.busy) return;
        rv.busy = true;
        const path = projectPath();
        document.getElementById('rv-proj').textContent = '· ' + (norm(path).split('/').filter(Boolean).pop() || path);
        document.getElementById('rv-stat').textContent = 'Wczytywanie…';
        try {
            const [g, s] = await Promise.all([
                post('git_status', { path }),
                post('snap_list', { path, backup_root: (typeof getBackupPath === 'function' ? getBackupPath() : '') }),
            ]);
            if (g.status !== 'ok') { setEmpty(`<i class="fa-solid fa-triangle-exclamation"></i><div>${esc(g.msg || 'Błąd')}</div>`); return; }
            rv.repo = g.repo ? g : null;
            rv.snaps = s.status === 'ok' ? s.snapshots : [];

            const sel = document.getElementById('rv-mode');
            let opts = '';
            if (rv.repo) opts += `<option value="git">Git: ${esc(g.branch)} — zmiany względem ostatniego commita</option>`;
            rv.snaps.forEach((sn) => { opts += `<option value="${esc(sn.path)}">Kopia zapasowa z ${esc(sn.label)}</option>`; });
            if (!opts) opts = '<option value="">(brak źródła porównania)</option>';
            sel.innerHTML = opts;
            if (![...sel.options].some((o) => o.value === rv.mode)) rv.mode = sel.options[0].value;
            sel.value = rv.mode;

            document.getElementById('rv-commit').style.display = rv.mode === 'git' && rv.repo ? '' : 'none';

            if (rv.mode === 'git' && rv.repo) {
                rv.files = g.files.map((f) => ({ ...f, checked: true }));
                rv.base = '';
                document.getElementById('rv-stat').textContent = g.truncated ? 'Pokazano pierwsze 800 zmian' : (g.shortstat || `${rv.files.length} zmienionych`);
            } else if (rv.mode) {
                const r = await post('snap_status', { path, base: rv.mode });
                if (r.status !== 'ok') { setEmpty(`<i class="fa-solid fa-triangle-exclamation"></i><div>${esc(r.msg)}</div>`); return; }
                rv.files = r.files.map((f) => ({ ...f, checked: false }));
                rv.base = rv.mode;
                document.getElementById('rv-stat').textContent = `${rv.files.length} różnic względem kopii`;
            } else {
                rv.files = [];
            }

            if (!rv.repo && !rv.snaps.length) {
                renderFiles();
                setEmpty(`<i class="fa-solid fa-code-branch"></i>
                    <div><b>Ten folder nie jest repozytorium git i nie ma kopii zapasowych.</b><br>
                    Zainicjuj git, aby zobaczyć, co AI zmieniło w kodzie i móc to cofnąć.</div>
                    <button class="rv-btn primary" id="rv-init"><i class="fa-solid fa-play"></i> Zainicjuj repozytorium git</button>
                    <div style="font-size:11px">lub zrób kopię: prawy przycisk na folderze → Wykonaj kopię</div>`);
                document.getElementById('rv-init').onclick = rvInitRepo;
                return;
            }

            const keep = !resetSel && rv.sel && rv.files.find((f) => f.file === rv.sel.file);
            renderFiles();
            if (keep) selectFile(keep);
            else if (rv.files.length) selectFile(rv.files[0]);
            else {
                rv.sel = null;
                document.getElementById('rv-path').textContent = '—';
                setEmpty('<i class="fa-regular fa-circle-check"></i><div><b>Brak zmian.</b><br>Kod jest identyczny ze źródłem porównania.</div>');
            }
        } finally {
            rv.busy = false;
            updateButtons();
        }
    }

    function visibleFiles() {
        const q = (document.getElementById('rv-filter').value || '').toLowerCase();
        return rv.files.filter((f) => !q || f.file.toLowerCase().includes(q));
    }

    function renderFiles() {
        const box = document.getElementById('rv-files');
        const isGit = rv.mode === 'git' && rv.repo;
        const list = visibleFiles();
        box.innerHTML = list.map((f, i) => {
            const parts = f.file.split('/');
            const name = parts.pop();
            const dir = parts.join('/');
            return `<div class="rv-row ${rv.sel && rv.sel.file === f.file ? 'sel' : ''}" data-i="${i}" title="${esc(f.file)}">
                ${isGit ? `<input type="checkbox" ${f.checked ? 'checked' : ''} data-chk="${i}">` : ''}
                <span class="rv-st st-${esc(f.status)}">${esc(f.status)}</span>
                <span class="rv-name">${esc(name)} <small>${esc(dir)}</small></span></div>`;
        }).join('') || '<div style="padding:16px; color:#888; font-size:12px;">Brak plików.</div>';

        box.onclick = (e) => {
            const chk = e.target.closest('[data-chk]');
            if (chk) { list[+chk.dataset.chk].checked = chk.checked; updateButtons(); e.stopPropagation(); return; }
            const row = e.target.closest('.rv-row');
            if (row) selectFile(list[+row.dataset.i]);
        };
        updateButtons();
    }

    function toggleAllChecks() {
        const all = rv.files.every((f) => f.checked);
        rv.files.forEach((f) => (f.checked = !all));
        renderFiles();
    }

    function updateButtons() {
        const isGit = rv.mode === 'git' && rv.repo;
        const n = rv.files.filter((f) => f.checked).length;
        const c = document.getElementById('rv-commit');
        if (c) {
            c.disabled = !isGit || n === 0;
            c.innerHTML = `<i class="fa-solid fa-check"></i> Zatwierdź${isGit && n ? ` (${n})` : ''}`;
        }
        const all = document.getElementById('rv-all');
        if (all) all.style.display = isGit ? '' : 'none';
        const has = !!rv.sel;
        document.getElementById('rv-revert').disabled = !has;
        document.getElementById('rv-open').disabled = !has || rv.sel.status === 'D';
        document.getElementById('rv-revert').innerHTML = `<i class="fa-solid fa-rotate-left"></i> ${isGit ? 'Cofnij zmiany' : 'Przywróć z kopii'}`;
    }

    async function selectFile(f) {
        rv.sel = f;
        renderFiles();
        document.getElementById('rv-path').textContent = f.file;
        const isGit = rv.mode === 'git' && rv.repo;
        const r = await post(isGit ? 'git_diff' : 'snap_diff', { path: projectPath(), file: f.file, base: rv.base });
        if (rv.sel !== f) return; // użytkownik wybrał już inny plik
        if (r.status !== 'ok') { setEmpty(`<i class="fa-solid fa-triangle-exclamation"></i><div>${esc(r.msg)}</div>`); return; }
        if (r.flag) {
            setEmpty(`<i class="fa-regular fa-file"></i><div><b>${r.flag === 'binary' ? 'Plik binarny' : 'Plik jest zbyt duży'}</b><br>Nie można pokazać porównania tekstowego.</div>`);
            return;
        }
        f.abs = r.abs;
        setEmpty('');
        if (!rv.diff) {
            rv.diff = monaco.editor.createDiffEditor(document.getElementById('rv-monaco'), {
                theme: 'vs-dark', automaticLayout: true, readOnly: true, renderSideBySide: true,
                originalEditable: false, ignoreTrimWhitespace: false, fontSize: 13, scrollBeyondLastLine: false,
                renderOverviewRuler: true, minimap: { enabled: false },
            });
        }
        disposeDiff();
        const lang = langFor(f.file);
        const o = monaco.editor.createModel(r.original ?? '', lang);
        const m = monaco.editor.createModel(r.modified ?? '', lang);
        rv.models = [o, m];
        rv.diff.setModel({ original: o, modified: m });
        updateButtons();
    }

    function rvOpenInEditor() {
        const f = rv.sel;
        if (!f) return;
        const abs = f.abs || (norm(rv.repo ? rv.repo.root : projectPath()) + '/' + f.file);
        closeReview();
        openFileFromSidebar(norm(abs), f.file.split('/').pop());
    }

    function rvAskRevert() {
        const f = rv.sel;
        if (!f) return;
        const isGit = rv.mode === 'git' && rv.repo;
        const untracked = f.status === '?' || f.status === 'A';
        const what = isGit
            ? (untracked ? 'Plik jest nowy — zostanie <b>usunięty z dysku</b>.' : 'Plik wróci do stanu z ostatniego commita. Zmiany zostaną <b>utracone</b>.')
            : 'Plik zostanie przywrócony z wybranej kopii zapasowej.';
        showModal('Cofnąć zmiany?', `<p style="margin:0 0 8px"><code>${esc(f.file)}</code></p><p style="margin:0">${what}</p>`, [
            { text: 'Anuluj', class: 'btn-secondary', onclick: 'closeModal()' },
            { text: isGit ? 'Cofnij zmiany' : 'Przywróć', class: 'btn-primary', onclick: 'rvDoRevert()' },
        ]);
    }
    window.rvDoRevert = async function () {
        closeModal();
        const f = rv.sel;
        if (!f) return;
        const isGit = rv.mode === 'git' && rv.repo;
        const r = await post(isGit ? 'git_revert' : 'snap_restore', { path: projectPath(), file: f.file, base: rv.base });
        if (r.status !== 'ok') { toast(r.msg || 'Nie udało się cofnąć', 'error'); return; }
        toast('Cofnięto: ' + f.file, 'success');
        // karta z tym plikiem: przeładuj z dysku przy najbliższym odczycie
        tabs.forEach((t) => { if (t.path && norm(t.path).endsWith('/' + f.file)) t.mtime = undefined; });
        rv.sel = null;
        refreshReview(true);
    };

    function rvAskCommit() {
        const chosen = rv.files.filter((f) => f.checked);
        if (!chosen.length) return;
        showModal('Zatwierdź zmiany (commit)', `
            <p class="hint" style="margin:0 0 8px;color:#999">Zostanie zatwierdzonych plików: <b style="color:#fff">${chosen.length}</b></p>
            <textarea id="rv-msg" class="rv-ta" placeholder="Krótki opis zmian, np. „Dodano filtr Ostatnie w drzewie projektów”"></textarea>`, [
            { text: 'Anuluj', class: 'btn-secondary', onclick: 'closeModal()' },
            { text: 'Zatwierdź', class: 'btn-primary', onclick: 'rvDoCommit()' },
        ]);
        setTimeout(() => document.getElementById('rv-msg')?.focus(), 50);
    }
    window.rvDoCommit = async function () {
        const msg = (document.getElementById('rv-msg')?.value || '').trim();
        if (!msg) { toast('Podaj opis zatwierdzenia.', 'error'); return; }
        const files = rv.files.filter((f) => f.checked).map((f) => f.file);
        closeModal();
        const r = await post('git_commit', { path: projectPath(), message: msg, files: files.length === rv.files.length ? [] : files });
        if (r.status !== 'ok') { toast(r.msg || 'Commit nie powiódł się', 'error'); return; }
        toast('Zatwierdzono zmiany.', 'success');
        refreshReview(true);
    };

    async function rvInitRepo() {
        const r = await post('git_init', { path: projectPath() });
        if (r.status !== 'ok') { toast(r.msg || 'Nie udało się zainicjować repozytorium', 'error'); return; }
        toast('Utworzono repozytorium i zapisano stan początkowy.', 'success');
        refreshReview(true);
    }

    /* Odznaka w stopce: gałąź + liczba zmian (zastępuje stare updateGitBranch) */
    async function refreshRepoBadge() {
        const item = document.querySelector('.footer-left .footer-item:nth-child(1)');
        if (!item) return;
        item.classList.add('rv-clickable');
        item.onclick = openReview;
        const path = projectPath();
        if (!path) return;
        const r = await post('git_status', { path, light: 1 });
        if (r.status === 'ok' && r.repo) {
            const n = r.files.length;
            item.innerHTML = `<span class="icon"><i class="fa-solid fa-code-branch"></i></span> ${esc(r.branch)}${n ? `<span class="rv-badge" title="Zmienione pliki">${n}${r.truncated ? '+' : ''}</span>` : ''}`;
            item.title = n ? `${n} zmienionych plików — kliknij, aby przejrzeć` : 'Brak zmian — kliknij, aby otworzyć przegląd';
        } else {
            item.innerHTML = '<span class="icon"><i class="fa-solid fa-code-branch"></i></span> —';
            item.title = 'Otwórz przegląd zmian';
        }
    }
    window.updateGitBranch = refreshRepoBadge;

    /* =========================================================
     *  2. AUTO-ODŚWIEŻANIE KART (zmiany z innych aplikacji / AI)
     * ========================================================= */
    let watchBusy = false;

    function changedLines(oldT, newT) {
        const a = String(oldT).split('\n'), b = String(newT).split('\n');
        let p = 0;
        while (p < a.length && p < b.length && a[p] === b[p]) p++;
        let s = 0;
        while (s < a.length - p && s < b.length - p && a[a.length - 1 - s] === b[b.length - 1 - s]) s++;
        const from = Math.min(p + 1, b.length);
        return { from, to: Math.max(from, b.length - s) };
    }

    function flashRange(r) {
        if (!r || !editor) return;
        const ids = editor.deltaDecorations([], [{
            range: new monaco.Range(r.from, 1, r.to, 1),
            options: { isWholeLine: true, className: 'rv-flash-line', linesDecorationsClassName: 'rv-flash-gutter' },
        }]);
        editor.revealLineInCenterIfOutsideViewport(r.from);
        setTimeout(() => editor.deltaDecorations(ids, []), 6500);
    }

    function applyExternal(tab, disk, silent) {
        const old = tab.id === activeTabId ? editor.getValue() : tab.content;
        const range = changedLines(old, disk);
        tab.content = disk;
        tab.modified = false;
        tab.conflict = null;
        if (tab.id === activeTabId) {
            const model = editor.getModel();
            const pos = editor.getPosition();
            const top = editor.getScrollTop();
            suppressChange = true;
            model.pushEditOperations([], [{ range: model.getFullModelRange(), text: disk }], () => null);
            suppressChange = false;
            if (pos) editor.setPosition(pos);
            editor.setScrollTop(top);
            flashRange(range);
            hideBanner();
            if (typeof isPreviewOpen !== 'undefined' && isPreviewOpen) updatePreview();
        } else {
            tab.flash = range;
            tab.reloaded = true;
        }
        renderTabs();
        if (!silent) toast(`Odświeżono „${tab.name}” — plik zmieniony poza edytorem`, 'info');
    }

    function showBanner(tab) {
        let b = document.getElementById('rv-banner');
        if (!b) {
            b = document.createElement('div');
            b.id = 'rv-banner';
            document.querySelector('.editor-area').appendChild(b);
        }
        b.innerHTML = `<div class="b-title"><i class="fa-solid fa-triangle-exclamation" style="color:var(--status-bar-bg)"></i> Plik zmieniony na dysku</div>
            <div>„${esc(tab.name)}” został zmieniony poza edytorem, a masz tu niezapisane zmiany.</div>
            <div class="b-actions">
                <button class="rv-btn primary" id="rv-b-load">Wczytaj z dysku</button>
                <button class="rv-btn" id="rv-b-keep">Zachowaj moją wersję</button>
            </div>`;
        b.classList.add('show');
        document.getElementById('rv-b-load').onclick = () => { const t = tab; applyExternal(t, t.conflict, false); };
        document.getElementById('rv-b-keep').onclick = () => { tab.conflict = null; hideBanner(); };
    }
    function hideBanner() { document.getElementById('rv-banner')?.classList.remove('show'); }

    async function onDiskChange(tab) {
        const fd = new FormData();
        fd.append('action', 'open');
        fd.append('path', tab.path);
        const data = await fetch(fileHandlerUrl, { method: 'POST', body: fd }).then((r) => r.json()).catch(() => null);
        if (!data || data.status !== 'ok') return;
        const local = tab.id === activeTabId ? editor.getValue() : tab.content;
        if (data.content === local) return;
        if (tab.modified) {
            tab.conflict = data.content;
            if (tab.id === activeTabId) showBanner(tab);
            else toast(`„${tab.name}” zmieniony na dysku (masz niezapisane zmiany)`, 'error');
        } else {
            applyExternal(tab, data.content, false);
        }
    }

    async function pollDisk() {
        if (watchBusy || document.hidden) return;
        const withPath = tabs.filter((t) => t.path);
        if (!withPath.length) return;
        watchBusy = true;
        try {
            const r = await post('stat', { paths: withPath.map((t) => t.path) });
            if (r.status !== 'ok') return;
            for (const t of withPath) {
                const info = r.files[t.path];
                if (!info || info.gone) continue;
                if (t.mtime === undefined) { t.mtime = info.m; t.size = info.s; continue; }
                if (info.m !== t.mtime || info.s !== t.size) {
                    t.mtime = info.m;
                    t.size = info.s;
                    await onDiskChange(t);
                }
            }
        } finally {
            watchBusy = false;
        }
    }

    // wywoływane z activateTab()
    window.rvOnActivate = function (tab) {
        if (tab.reloaded) { tab.reloaded = false; renderTabs(); }
        if (tab.flash) { const f = tab.flash; tab.flash = null; setTimeout(() => flashRange(f), 60); }
        if (tab.conflict) showBanner(tab); else hideBanner();
    };

    /* =========================================================
     *  3. SZYBKIE POLECENIA
     * ========================================================= */
    window.runQuick = async function (key) {
        const t = tabs.find((x) => x.id === activeTabId);
        showModal('Uruchamianie…', '<div style="padding:10px;color:#999"><i class="fa-solid fa-spinner fa-spin"></i> Proszę czekać…</div>');
        const r = await post('quick', { key, path: projectPath(), file: t && t.path ? t.path : '' });
        if (r.status !== 'ok') {
            showModal('Nie można wykonać', `<div class="rv-pre err">${esc(r.msg)}</div>`);
            return;
        }
        showModal((r.ok ? '✔ ' : '✖ ') + r.title, `<pre class="rv-pre ${r.ok ? 'ok' : 'err'}">${esc(r.output)}</pre>`);
    };

    /* =========================================================
     *  4. PACZKA KONTEKSTU DLA AI
     * ========================================================= */
    const cx = { built: false, files: [], name: '' };

    function buildCtx() {
        if (cx.built) return;
        cx.built = true;
        const el = document.createElement('div');
        el.id = 'cx-panel';
        el.className = 'rv-overlay';
        el.innerHTML = `
        <div class="rv-window narrow">
            <div class="rv-head">
                <div class="rv-title"><i class="fa-solid fa-box-archive"></i> Paczka kontekstu dla AI <span id="cx-proj" style="color:#888;font-weight:400"></span></div>
                <div class="rv-spacer"></div>
                <button class="rv-x" id="cx-close"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="rv-content">
                <p class="hint">Zaznacz pliki, a złożę je w jeden tekst (drzewo + treść) do wklejenia do czatu z AI. Sekrety (.env, klucze) są wykluczone, a wartości w stylu <code>api_key = "…"</code> maskowane.</p>
                <div style="display:flex;gap:6px;margin-bottom:8px;flex-wrap:wrap">
                    <input type="text" id="cx-filter" class="rv-ta" style="min-height:0;width:auto;flex:1;min-width:160px;padding:6px 10px" placeholder="Filtruj…" autocomplete="off">
                    <button class="rv-btn" id="cx-code">Tylko kod</button>
                    <button class="rv-btn" id="cx-changed">Tylko zmienione</button>
                    <button class="rv-btn" id="cx-none">Wyczyść</button>
                </div>
                <div class="rv-files" id="cx-files" style="max-height:38vh;border:1px solid #3e3e42;border-radius:4px;background:#252526"></div>
            </div>
            <div class="rv-stats"><span id="cx-sum"></span><div class="rv-spacer"></div>
                <button class="rv-btn" id="cx-tab"><i class="fa-solid fa-file-lines"></i> Otwórz w karcie</button>
                <button class="rv-btn" id="cx-dl"><i class="fa-solid fa-download"></i> Pobierz .md</button>
                <button class="rv-btn primary" id="cx-copy"><i class="fa-solid fa-copy"></i> Kopiuj do schowka</button>
            </div>
        </div>`;
        document.body.appendChild(el);
        el.addEventListener('mousedown', (e) => { if (e.target === el) el.classList.remove('show'); });
        document.getElementById('cx-close').onclick = () => el.classList.remove('show');
        document.getElementById('cx-filter').oninput = renderCtx;
        document.getElementById('cx-none').onclick = () => { cx.files.forEach((f) => (f.checked = false)); renderCtx(); };
        document.getElementById('cx-code').onclick = () => {
            const code = /\.(php|js|mjs|ts|tsx|jsx|css|scss|html|htm|json|md|py|sql|xml|ya?ml|sh|bat|ini)$/i;
            cx.files.forEach((f) => (f.checked = !f.skip && code.test(f.file)));
            renderCtx();
        };
        document.getElementById('cx-changed').onclick = async () => {
            const g = await post('git_status', { path: projectPath() });
            if (g.status !== 'ok' || !g.repo) { toast('To nie jest repozytorium git.', 'error'); return; }
            const set = new Set(g.files.map((f) => f.file));
            cx.files.forEach((f) => (f.checked = !f.skip && set.has(f.file)));
            renderCtx();
        };
        document.getElementById('cx-copy').onclick = () => ctxOutput('copy');
        document.getElementById('cx-dl').onclick = () => ctxOutput('download');
        document.getElementById('cx-tab').onclick = () => ctxOutput('tab');
        document.getElementById('cx-files').onclick = (e) => {
            const c = e.target.closest('[data-c]');
            if (c) { const f = cx.files[+c.dataset.c]; f.checked = c.checked; updateCtxSum(); }
        };
    }

    async function openContext() {
        buildCtx();
        const el = document.getElementById('cx-panel');
        el.classList.add('show');
        document.getElementById('cx-files').innerHTML = '<div style="padding:14px;color:#888">Wczytywanie…</div>';
        const r = await post('ctx_list', { path: projectPath() });
        if (r.status !== 'ok') { document.getElementById('cx-files').innerHTML = `<div style="padding:14px;color:#f14c4c">${esc(r.msg)}</div>`; return; }
        cx.name = r.name;
        document.getElementById('cx-proj').textContent = '· ' + r.name;
        const total = r.files.filter((f) => !f.skip).reduce((s, f) => s + f.size, 0);
        cx.files = r.files.map((f) => ({ ...f, checked: !f.skip && total < 120000 })); // małe projekty zaznacz od razu
        document.getElementById('cx-filter').value = '';
        renderCtx();
    }

    function renderCtx() {
        const q = document.getElementById('cx-filter').value.toLowerCase();
        const chips = { secret: 'sekret', binary: 'binarny', big: 'duży' };
        document.getElementById('cx-files').innerHTML = cx.files.map((f, i) => {
            if (q && !f.file.toLowerCase().includes(q)) return '';
            return `<label class="rv-row ${f.skip ? 'skipped' : ''}"><input type="checkbox" data-c="${i}" ${f.checked ? 'checked' : ''} ${f.skip ? 'disabled' : ''}>
                <span class="rv-name">${esc(f.file)}</span>${f.skip ? `<span class="rv-chip">${chips[f.skip]}</span>` : `<span class="rv-chip">${(f.size / 1024).toFixed(1)} KB</span>`}</label>`;
        }).join('') || '<div style="padding:14px;color:#888">Brak plików.</div>';
        updateCtxSum();
    }

    function updateCtxSum() {
        const sel = cx.files.filter((f) => f.checked);
        const chars = sel.reduce((s, f) => s + f.size, 0);
        const tokens = Math.round(chars / 3.8);
        const warn = tokens > 100000 ? ' <span style="color:#f14c4c">— bardzo dużo, rozważ zawężenie</span>' : tokens > 30000 ? ' <span style="color:#e2c08d">— dużo</span>' : '';
        document.getElementById('cx-sum').innerHTML = `Wybrano <b>${sel.length}</b> plików · ok. <b>${tokens.toLocaleString('pl-PL')}</b> tokenów${warn}`;
    }

    async function ctxOutput(kind) {
        const files = cx.files.filter((f) => f.checked).map((f) => f.file);
        if (!files.length) { toast('Zaznacz przynajmniej jeden plik.', 'error'); return; }
        const r = await post('ctx_pack', { path: projectPath(), files });
        if (r.status !== 'ok') { toast(r.msg || 'Błąd', 'error'); return; }
        const note = r.redacted ? ` (zamaskowano ${r.redacted} sekretów)` : '';
        if (kind === 'copy') {
            try { await navigator.clipboard.writeText(r.text); toast(`Skopiowano ${r.files} plików do schowka${note}.`, 'success'); }
            catch (e) { toast('Schowek zablokowany — użyj „Pobierz” lub „Otwórz w karcie”.', 'error'); }
        } else if (kind === 'download') {
            const a = document.createElement('a');
            a.href = URL.createObjectURL(new Blob([r.text], { type: 'text/markdown;charset=utf-8' }));
            a.download = `kontekst-${cx.name}.md`;
            document.body.appendChild(a); a.click(); a.remove();
            toast('Pobrano paczkę kontekstu' + note, 'success');
        } else {
            createNewTab(`kontekst-${cx.name}.md`, r.text);
            document.getElementById('cx-panel').classList.remove('show');
        }
    }

    /* =========================================================
     *  5. PLIKI STERUJĄCE AI
     * ========================================================= */
    const TEMPLATES = {
        'CLAUDE.md': `# Zasady projektu\n\n## Opis\nKrótko: co to za aplikacja i dla kogo.\n\n## Stos technologiczny\n- PHP / HTML / JS / CSS\n- Baza: SQLite lub MariaDB (XAMPP)\n\n## Zasady kodu\n- Zachowuj styl otwartego kodu (nazwy, wcięcia, komentarze jak w sąsiednich plikach).\n- Nie zmieniaj plików poza zakresem zadania.\n- Wszystkie komunikaty dla użytkownika po polsku, wyłącznie z aplikacji (bez alert/confirm).\n\n## Po zmianach\n- Sprawdź składnię (php -l) i przetestuj w przeglądarce.\n- Nie commituj bez prośby.\n`,
        'AGENTS.md': `# Instrukcje dla agentów AI\n\n## Kontekst\nOpis projektu i struktura katalogów.\n\n## Polecenia\n- Uruchomienie: XAMPP → http://localhost/nazwa-projektu\n- Testy: (uzupełnij)\n\n## Ograniczenia\n- Nie usuwaj danych użytkownika.\n- Pytaj przed zmianą schematu bazy.\n`,
    };

    async function openAiFiles() {
        const path = projectPath();
        const r = await post('ai_files', { path });
        if (r.status !== 'ok') { toast(r.msg || 'Błąd', 'error'); return; }
        const have = new Set(r.files.map((f) => f.file));
        let html = '<p class="hint" style="color:#999;margin:0 0 8px">Pliki, które kierują pracą AI w tym projekcie:</p>';
        html += r.files.length ? r.files.map((f, i) => `<div class="rv-aifile"><i class="fa-regular fa-file-lines"></i><span class="n">${esc(f.file)}</span>
            <button class="rv-btn" onclick="rvOpenAiFile(${i})">Otwórz</button></div>`).join('') : '<div style="color:#888;padding:6px 0">Brak plików sterujących.</div>';
        const missing = Object.keys(TEMPLATES).filter((n) => !have.has(n));
        if (missing.length) {
            html += '<div style="margin-top:12px;border-top:1px solid #3e3e42;padding-top:10px;color:#999;font-size:12px">Utwórz z szablonu:</div>';
            html += missing.map((n) => `<div class="rv-aifile"><i class="fa-solid fa-plus"></i><span class="n">${esc(n)}</span><button class="rv-btn primary" onclick="rvCreateAiFile('${n}')">Utwórz</button></div>`).join('');
        }
        window.__rvAi = r.files;
        showModal('Pliki sterujące AI', html);
    }
    window.rvOpenAiFile = function (i) {
        const f = window.__rvAi[i];
        closeModal();
        openFileFromSidebar(norm(f.abs), f.file.split('/').pop());
    };
    window.rvCreateAiFile = async function (name) {
        const path = norm(projectPath()).replace(/\/$/, '') + '/' + name;
        const fd = new FormData();
        fd.append('action', 'save');
        fd.append('path', path);
        fd.append('content', TEMPLATES[name]);
        const r = await fetch(fileHandlerUrl, { method: 'POST', body: fd }).then((x) => x.json()).catch(() => ({ status: 'error' }));
        closeModal();
        if (r.status !== 'ok') { toast('Nie udało się utworzyć pliku.', 'error'); return; }
        toast(`Utworzono ${name}`, 'success');
        refreshSidebar();
        openFileFromSidebar(path, name);
    };

    /* =========================================================
     *  START, MENU, SKRÓTY
     * ========================================================= */
    /* Nowy plik / nowy folder w eksploratorze */
    window.edNewItem = function (kind, dir) {
        dir = norm(dir || projectPath());
        if (!dir) { toast('Najpierw otwórz folder projektu.', 'error'); return; }
        const isFile = kind === 'file';
        window.__edNew = { kind, dir };
        showModal(isFile ? 'Nowy plik' : 'Nowy folder', `
            <p style="margin:0 0 8px;color:#999;font-size:12px;word-break:break-all">W: ${esc(dir)}</p>
            <input id="ed-new-name" class="modal-input" style="margin-top:0" autocomplete="off" spellcheck="false" value="${isFile ? 'nowy-plik.txt' : 'nowy-folder'}" onkeydown="if(event.key==='Enter')edDoNew()">
            <div id="ed-new-err" style="color:#f14c4c;font-size:12px;min-height:16px;margin-top:6px"></div>`, [
            { text: 'Anuluj', class: 'btn-secondary', onclick: 'closeModal()' },
            { text: 'Utwórz', class: 'btn-primary', onclick: 'edDoNew()' },
        ]);
        setTimeout(() => {
            const i = document.getElementById('ed-new-name');
            if (!i) return;
            i.focus();
            const dot = i.value.lastIndexOf('.');
            i.setSelectionRange(0, dot > 0 ? dot : i.value.length);
        }, 40);
    };
    window.edNewFromCtx = function (kind) {
        hideContextMenu();
        if (!contextMenuTarget) return;
        const p = norm(contextMenuTarget.path);
        window.edNewItem(kind, contextMenuTarget.type === 'folder' ? p : p.replace(/\/[^\/]*$/, ''));
    };
    window.edDoNew = async function () {
        const st = window.__edNew;
        const inp = document.getElementById('ed-new-name');
        if (!st || !inp) return;
        const name = inp.value.trim();
        const err = document.getElementById('ed-new-err');
        if (!name) { err.textContent = 'Podaj nazwę.'; return; }
        const fd = new FormData();
        fd.append('action', st.kind === 'file' ? 'create_file' : 'create_folder');
        fd.append('path', st.dir);
        fd.append('name', name);
        const r = await fetch('../fs_handler.php', { method: 'POST', body: fd }).then((x) => x.json()).catch(() => ({ status: 'error', msg: 'Błąd połączenia z serwerem.' }));
        if (r.status !== 'ok') { err.textContent = r.msg || 'Nie udało się utworzyć.'; return; }
        closeModal();
        refreshSidebar();
        toast((st.kind === 'file' ? 'Utworzono plik: ' : 'Utworzono folder: ') + name, 'success');
        if (st.kind === 'file') openFileFromSidebar(norm(r.path), name);
    };

    /* Okno pomocy (osobna strona pomoc.html w ramce) */
    window.openHelp = function (anchor) {
        let el = document.getElementById('help-panel');
        if (!el) {
            el = document.createElement('div');
            el.id = 'help-panel';
            el.className = 'rv-overlay';
            el.innerHTML = '<div class="rv-window"><div class="rv-head"><div class="rv-title"><i class="fa-solid fa-circle-question"></i> Pomoc: MrPrompt Edytor</div><div class="rv-spacer"></div><a class="rv-btn" href="pomoc.html" target="_blank" rel="noopener"><i class="fa-solid fa-up-right-from-square"></i> W nowej karcie</a><button class="rv-x" id="help-close" title="Zamknij (Esc)"><i class="fa-solid fa-xmark"></i></button></div><iframe id="help-frame" style="flex:1;border:0;background:#1e1e1e" title="Pomoc"></iframe></div>';
            document.body.appendChild(el);
            const close = () => el.classList.remove('show');
            document.getElementById('help-close').onclick = close;
            el.addEventListener('mousedown', (e) => { if (e.target === el) close(); });
            document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && el.classList.contains('show')) close(); });
        }
        const f = document.getElementById('help-frame');
        const url = 'pomoc.html' + (anchor ? '#' + anchor : '');
        if (!f.getAttribute('src')) f.setAttribute('src', url);
        else if (anchor) f.contentWindow.location.hash = anchor;
        el.classList.add('show');
    };
    document.addEventListener('keydown', (e) => {
        if (e.key === 'F1') { e.preventDefault(); window.openHelp(); }
    });

    window.openReviewPanel = openReview;
    window.openContextPack = openContext;
    window.openAiFiles = openAiFiles;

    // pamiętaj ostatnio otwarty folder projektu
    const _loadSidebar = window.loadSidebar;
    window.loadSidebar = function (p) {
        try { localStorage.setItem('mrp_last_project', p); } catch (e) { /* brak dostępu do storage */ }
        const res = _loadSidebar(p);
        setTimeout(refreshRepoBadge, 200);
        return res;
    };

    // uruchamiane z app.js po starcie Monaco
    window.mrpStartup = function () {
        const q = new URLSearchParams(location.search);
        let project = q.get('project') || '';
        if (!project) { try { project = localStorage.getItem('mrp_last_project') || ''; } catch (e) { /* ignoruj */ } }
        project = norm(project || window.MRP_DOCROOT || 'C:/xampp/htdocs');
        browserCurrentPath = project;
        loadSidebar(project);
        const open = q.get('open');
        if (open) openFileFromSidebar(norm(open), norm(open).split('/').pop());
        if (q.get('panel') === 'changes') setTimeout(openReview, 400);
        setInterval(pollDisk, 2000);
        window.addEventListener('focus', pollDisk);
        setInterval(() => { if (!document.hidden && !isReviewOpen()) refreshRepoBadge(); }, 20000);
    };

    document.addEventListener('keydown', (e) => {
        if (e.ctrlKey && e.shiftKey && (e.key === 'G' || e.key === 'g')) { e.preventDefault(); openReview(); }
    });
})();
