/* Diagnostyka panelu: logi błędów, porty i procesy, bazy (zrzuty), kontrola projektu, audyt, kreator nowego projektu.
   Ładowany po głównym skrypcie index.php (korzysta z jego funkcji: escapeHtml, showToast, showCustomConfirm, setCtx…). */
(function () {
    'use strict';

    const e = escapeHtml;
    const $ = (s, r = document) => r.querySelector(s);
    const fmtBytes = (b) => {
        if (!b) return '0 B';
        const u = ['B', 'KB', 'MB', 'GB'], i = Math.min(3, Math.floor(Math.log(b) / Math.log(1024)));
        return (b / Math.pow(1024, i)).toFixed(i ? 1 : 0) + ' ' + u[i];
    };
    const baseName = (p) => String(p || '').replace(/[\\/]+$/, '').split(/[\\/]/).pop();
    const norm = (p) => String(p || '').replace(/\\/g, '/');

    function tcall(fields) {
        const fd = new FormData();
        Object.entries(fields).forEach(([k, v]) => { if (v !== undefined && v !== null) fd.append(k, v); });
        return fetch('mrprompt_server/tools_handler.php', { method: 'POST', body: fd })
            .then((r) => r.json())
            .catch(() => ({ status: 'error', msg: 'Błąd połączenia z serwerem.' }));
    }

    function ago(ts, now) {
        if (!ts) return '';
        const d = Math.max(0, (now || Date.now() / 1000) - ts);
        if (d < 60) return 'przed chwilą';
        if (d < 3600) return Math.round(d / 60) + ' min temu';
        if (d < 86400) return Math.round(d / 3600) + ' godz. temu';
        return Math.round(d / 86400) + ' dni temu';
    }
    async function copyText(text, okMsg) {
        try { await navigator.clipboard.writeText(text); showToast(okMsg || 'Skopiowano do schowka', 'success'); }
        catch (err) { showToast('Schowek zablokowany przez przeglądarkę', 'error'); }
    }

    /* ---------- okno narzędzi ---------- */
    let mEl = null;
    function modal(html, wide) {
        if (!mEl) {
            mEl = document.createElement('div');
            mEl.className = 'dgm';
            mEl.innerHTML = '<div class="dgm-box"></div>';
            document.body.appendChild(mEl);
            mEl.addEventListener('mousedown', (ev) => { if (ev.target === mEl) closeM(); });
            document.addEventListener('keydown', (ev) => { if (ev.key === 'Escape' && mEl.classList.contains('open')) closeM(); });
        }
        const box = mEl.firstElementChild;
        box.style.width = wide ? 'min(900px, 100%)' : '';
        box.innerHTML = html;
        mEl.classList.add('open');
        const x = $('.dgm-x', box);
        if (x) x.onclick = closeM;
        return box;
    }
    function closeM() { if (mEl) mEl.classList.remove('open'); }
    const mHead = (icon, title) => `<div class="dgm-head"><h3><i class="${icon}" style="color:var(--accent2)"></i>${title}</h3><button class="dgm-x" title="Zamknij (Esc)"><i class="fa-solid fa-xmark"></i></button></div>`;

    /* =====================================================================
     *  STRONA „DIAGNOSTYKA”
     * ===================================================================== */
    const DG = { tab: 'logs', src: { php: true, apache: true }, lv: { crit: true, warning: true, deprecated: false, notice: false }, project: '', live: false, timer: null, last: null };
    const OFF_KEY = 'mrp_log_offsets';
    const getOffsets = () => { try { return JSON.parse(localStorage.getItem(OFF_KEY) || '{}'); } catch (err) { return {}; } };
    const setOffsets = (o) => { try { localStorage.setItem(OFF_KEY, JSON.stringify(o)); } catch (err) { /* brak dostępu do storage */ } };

    window.openDiag = function (tab, opts) {
        opts = opts || {};
        if (tab) DG.tab = tab;
        if (opts.project !== undefined) DG.project = opts.project;
        const main = document.getElementById('mainContent');
        main.style.padding = '28px 32px';
        main.style.alignItems = 'stretch';
        main.style.justifyContent = 'flex-start';
        main.innerHTML = `<div class="dg">
            <div class="dg-head"><h2><i class="fa-solid fa-stethoscope"></i>Diagnostyka</h2>
                <div class="dg-tabs">
                    <button class="dg-tab" data-t="logs"><i class="fa-solid fa-bug"></i>Logi błędów</button>
                    <button class="dg-tab" data-t="ports"><i class="fa-solid fa-network-wired"></i>Porty i procesy</button>
                    <button class="dg-tab" data-t="db"><i class="fa-solid fa-database"></i>Bazy danych</button>
                </div></div>
            <div id="dgBody"></div></div>`;
        if (typeof setCtx === 'function') setCtx('Diagnostyka', '');
        main.querySelectorAll('.dg-tab').forEach((b) => b.onclick = () => { DG.tab = b.dataset.t; openDiagTab(); });
        openDiagTab();
    };

    function openDiagTab() {
        stopLive();
        document.querySelectorAll('.dg-tab').forEach((b) => b.classList.toggle('on', b.dataset.t === DG.tab));
        if (DG.tab === 'logs') renderLogs();
        else if (DG.tab === 'ports') renderPorts();
        else renderDbs();
    }
    function stopLive() { if (DG.timer) { clearInterval(DG.timer); DG.timer = null; } }

    /* ---------- LOGI ---------- */
    function renderLogs() {
        const off = getOffsets();
        const bookmarked = Object.keys(off).length > 0;
        $('#dgBody').innerHTML = `
            <div class="dg-note"><i class="fa-solid fa-lightbulb"></i> Białą stronę po zmianie od AI zwykle wyjaśnia ten log. Kliknij <b>Kopiuj dla AI</b> przy błędzie: dostaniesz gotowy opis z plikiem, linią i fragmentem kodu do wklejenia.</div>
            <div class="dg-bar">
                <span class="dg-chip ${DG.src.php ? 'on' : ''}" data-src="php">Log PHP</span>
                <span class="dg-chip ${DG.src.apache ? 'on' : ''}" data-src="apache">Log Apache</span>
                <span style="width:1px;height:20px;background:var(--border)"></span>
                <span class="dg-chip ${DG.lv.crit ? 'on' : ''}" data-lv="crit">Krytyczne</span>
                <span class="dg-chip ${DG.lv.warning ? 'on' : ''}" data-lv="warning">Ostrzeżenia</span>
                <span class="dg-chip ${DG.lv.deprecated ? 'on' : ''}" data-lv="deprecated">Przestarzałe</span>
                <span class="dg-chip ${DG.lv.notice ? 'on' : ''}" data-lv="notice">Uwagi</span>
            </div>
            <div class="dg-bar">
                <select class="dg-sel" id="dgProj"><option value="">Wszystkie projekty</option></select>
                <button class="dg-btn" id="dgMark" title="Ukrywa wszystko, co jest w logu do tej chwili: zobaczysz tylko nowe błędy">${bookmarked ? '<i class="fa-solid fa-eye"></i> Pokaż też starsze' : '<i class="fa-solid fa-flag"></i> Tylko nowe od teraz'}</button>
                <button class="dg-btn" id="dgRefresh"><i class="fa-solid fa-rotate"></i> Odśwież</button>
                <button class="dg-btn" id="dgCopyAll" title="Kopiuje 10 najnowszych widocznych błędów"><i class="fa-solid fa-copy"></i> Kopiuj widoczne dla AI</button>
                <span class="dg-live ${DG.live ? 'on' : ''}" id="dgLive"><i class="dot"></i> Na żywo</span>
                <span id="dgCounts" style="margin-left:auto;font-size:12px;color:var(--text-muted)"></span>
            </div>
            <div id="dgLogs"><div class="dg-spin"><i class="fa-solid fa-spinner fa-spin"></i> Wczytywanie logów…</div></div>`;
        $('#dgBody').onclick = (ev) => {
            const s = ev.target.closest('[data-src]');
            if (s) { DG.src[s.dataset.src] = !DG.src[s.dataset.src]; if (!DG.src.php && !DG.src.apache) DG.src[s.dataset.src] = true; renderLogs(); return; }
            const l = ev.target.closest('[data-lv]');
            if (l) { DG.lv[l.dataset.lv] = !DG.lv[l.dataset.lv]; renderLogs(); return; }
            logAction(ev);
        };
        $('#dgProj').onchange = (ev) => { DG.project = ev.target.value; loadLogs(); };
        $('#dgRefresh').onclick = () => loadLogs();
        $('#dgLive').onclick = () => { DG.live = !DG.live; $('#dgLive').classList.toggle('on', DG.live); DG.live ? startLive() : stopLive(); };
        $('#dgMark').onclick = () => {
            if (Object.keys(getOffsets()).length) { setOffsets({}); showToast('Pokazuję też starsze wpisy', 'info'); renderLogs(); return; }
            const o = {};
            if (DG.last) Object.entries(DG.last.files).forEach(([k, f]) => { o[k] = f.size; });
            setOffsets(o);
            showToast('Od teraz widzisz tylko nowe błędy', 'success');
            renderLogs();
        };
        $('#dgCopyAll').onclick = copyVisible;
        if (DG.live) startLive();
        loadLogs();
    }
    function startLive() { stopLive(); DG.timer = setInterval(() => { if (!$('#dgLogs')) return stopLive(); if (!document.hidden) loadLogs(true); }, 3000); }

    async function loadLogs(silent) {
        const sources = Object.keys(DG.src).filter((k) => DG.src[k]).join(',');
        const levels = [DG.lv.crit ? 'fatal,parse' : '', DG.lv.warning ? 'warning' : '', DG.lv.deprecated ? 'deprecated' : '', DG.lv.notice ? 'notice' : ''].filter(Boolean).join(',') || 'fatal,parse';
        const r = await tcall({ action: 'log_read', sources, levels, project: DG.project, offsets: JSON.stringify(getOffsets()) });
        const box = $('#dgLogs');
        if (!box) return;
        if (r.status !== 'ok') { box.innerHTML = `<div class="dg-empty"><i class="fa-solid fa-triangle-exclamation"></i>${e(r.msg || 'Błąd')}</div>`; return; }
        // nie odbudowuj listy, gdy nic się nie zmieniło (spokojny tryb na żywo)
        const sig = JSON.stringify(r.entries.map((x) => [x.time, x.message, x.count]));
        if (silent && DG.last && DG.last.sig === sig) { DG.last.files = r.files; return; }
        r.sig = sig;
        DG.last = r;
        const sel = $('#dgProj');
        if (sel) {
            const names = Object.keys(r.projects);
            if (DG.project && !names.includes(DG.project)) names.unshift(DG.project);
            sel.innerHTML = '<option value="">Wszystkie projekty</option>' + names.map((n) => `<option value="${e(n)}" ${n === DG.project ? 'selected' : ''}>${e(n)}${r.projects[n] ? ' (' + r.projects[n] + ')' : ''}</option>`).join('');
        }
        const c = r.counts;
        $('#dgCounts').textContent = `Krytyczne: ${c.fatal + c.parse} · Ostrzeżenia: ${c.warning}${c.deprecated ? ' · Przestarzałe: ' + c.deprecated : ''}${c.notice ? ' · Uwagi: ' + c.notice : ''}`;
        if (!r.entries.length) {
            const missing = Object.values(r.files).filter((f) => !f.exists).length;
            box.innerHTML = `<div class="dg-empty"><i class="fa-regular fa-circle-check"></i><b>Brak błędów do pokazania.</b><br>${missing ? 'Nie znaleziono niektórych plików logów.' : 'Log jest czysty przy wybranych filtrach.'}<br><small>Sprawdzane pliki: ${Object.values(r.files).map((f) => e(f.path)).join(', ')}</small></div>`;
            return;
        }
        box.innerHTML = r.entries.map((en, i) => `<div class="lg ${en.level}" data-i="${i}" ${silent ? 'style="animation:none"' : `style="animation-delay:${Math.min(i, 10) * 25}ms"`}>
            <div class="lg-top"><span class="lv ${en.level}">${({ fatal: 'krytyczny', parse: 'składnia', warning: 'ostrzeżenie', deprecated: 'przestarzałe', notice: 'uwaga', other: 'inne' })[en.level]}</span>
                ${en.project ? `<span class="lg-proj" data-a="proj" data-p="${e(en.project)}"><i class="fa-solid fa-folder"></i> ${e(en.project)}</span>` : ''}
                <span>${en.source === 'php' ? 'log PHP' : 'log Apache'}</span><span>${ago(en.time, r.now)}</span>
                ${en.count > 1 ? `<span class="lg-cnt" title="Identyczny błąd powtórzył się">×${en.count}</span>` : ''}</div>
            <div class="lg-msg">${e(en.message)}</div>
            ${en.file ? `<div class="lg-file">${e(en.file)}:${en.line}</div>` : ''}
            <div class="lg-act">
                <button class="dg-btn sm primary" data-a="copy"><i class="fa-solid fa-copy"></i> Kopiuj dla AI</button>
                ${en.file && en.line ? '<button class="dg-btn sm" data-a="code"><i class="fa-solid fa-code"></i> Pokaż kod</button>' : ''}
                ${en.file ? '<button class="dg-btn sm" data-a="editor"><i class="fa-solid fa-up-right-from-square"></i> Otwórz w Edytorze</button>' : ''}
                ${en.detail ? '<button class="dg-btn sm" data-a="detail"><i class="fa-solid fa-layer-group"></i> Ślad stosu</button>' : ''}
            </div><div class="lg-extra"></div></div>`).join('');
    }

    async function aiText(en) {
        let code = '';
        if (en.file && en.line) {
            const r = await tcall({ action: 'log_context', file: en.file, line: en.line });
            if (r.status === 'ok') code = r.lines.map((l) => (l.hit ? '> ' : '  ') + String(l.n).padStart(4) + ' | ' + l.t).join('\n');
        }
        return `Błąd ${String(en.level).toUpperCase()} w projekcie ${en.project || '(nieznany)'} (PHP / XAMPP, Windows):\n${en.message}\n`
            + (en.file ? `Plik: ${en.file}:${en.line}\n` : '')
            + (en.count > 1 ? `Błąd powtórzył się ${en.count} razy.\n` : '')
            + (en.detail ? `\nŚlad stosu:\n${en.detail}\n` : '')
            + (code ? `\nKod wokół linii ${en.line}:\n\`\`\`php\n${code}\n\`\`\`\n` : '')
            + '\nZnajdź przyczynę i popraw ją w kodzie (nie tylko objaw). Po poprawce sprawdź składnię (php -l).';
    }

    async function logAction(ev) {
        const b = ev.target.closest('[data-a]');
        if (!b) return;
        const card = b.closest('.lg');
        const en = card ? DG.last.entries[+card.dataset.i] : null;
        const a = b.dataset.a;
        if (a === 'proj') { DG.project = b.dataset.p; renderLogs(); return; }
        if (!en) return;
        if (a === 'copy') { copyText(await aiText(en), 'Skopiowano opis błędu dla AI'); }
        else if (a === 'editor') {
            const proj = en.project ? norm(en.file).split('/').slice(0, norm(en.file).toLowerCase().indexOf('/' + en.project.toLowerCase() + '/') + en.project.length + 2).join('/') : '';
            window.open('mrprompt_server/edytor/index.php?open=' + encodeURIComponent(en.file) + (proj ? '&project=' + encodeURIComponent(proj) : ''), '_blank');
        } else if (a === 'code') {
            const ex = $('.lg-extra', card);
            if (ex.dataset.open) { ex.innerHTML = ''; ex.dataset.open = ''; return; }
            const r = await tcall({ action: 'log_context', file: en.file, line: en.line });
            if (r.status !== 'ok') { showToast(r.msg || 'Nie można odczytać pliku', 'error'); return; }
            ex.dataset.open = '1';
            ex.innerHTML = '<div class="lg-code">' + r.lines.map((l) => `<div class="${l.hit ? 'hit' : ''}"><span class="n">${l.n}</span>${e(l.t)}</div>`).join('') + '</div>';
        } else if (a === 'detail') {
            const ex = $('.lg-extra', card);
            ex.innerHTML = ex.innerHTML ? '' : `<div class="lg-detail">${e(en.detail)}</div>`;
        }
    }

    async function copyVisible() {
        const list = (DG.last && DG.last.entries || []).slice(0, 10);
        if (!list.length) { showToast('Brak błędów do skopiowania', 'info'); return; }
        const parts = [];
        for (const en of list) parts.push(await aiText(en));
        copyText(`Poniżej ${list.length} ostatnich błędów z logów (PHP / XAMPP). Uporządkuj je i popraw według ważności:\n\n` + parts.map((p, i) => `--- Błąd ${i + 1} ---\n${p}`).join('\n\n'), `Skopiowano ${list.length} błędów dla AI`);
    }

    /* ---------- PORTY I PROCESY ---------- */
    async function renderPorts() {
        $('#dgBody').innerHTML = `<div class="dg-note"><i class="fa-solid fa-lightbulb"></i> Tu widzisz wszystko, co nasłuchuje na portach Twojego komputera. Zapomniane serwery deweloperskie (np. <code>php -S</code>, Node, Vite) blokują porty i zajmują pamięć: możesz je stąd zakończyć.</div>
            <div class="dg-bar"><button class="dg-btn" id="dgPRef"><i class="fa-solid fa-rotate"></i> Odśwież</button><span id="dgPInfo" style="margin-left:auto;font-size:12px;color:var(--text-muted)"></span></div>
            <div id="dgPorts"><div class="dg-spin"><i class="fa-solid fa-spinner fa-spin"></i> Sprawdzam porty (to trwa około sekundy)…</div></div>`;
        $('#dgPRef').onclick = renderPorts;
        const r = await tcall({ action: 'ports_list' });
        const box = $('#dgPorts');
        if (!box) return;
        if (r.status !== 'ok') { box.innerHTML = `<div class="dg-empty">${e(r.msg || 'Błąd')}</div>`; return; }
        const kindLbl = { dev: 'serwer dev', xampp: 'XAMPP', system: 'system', inne: 'program' };
        window.__dgPorts = r.rows;
        const dev = r.rows.filter((x) => x.kind === 'dev').length;
        $('#dgPInfo').textContent = `${r.rows.length} procesów nasłuchuje · serwerów deweloperskich: ${dev}`;
        box.innerHTML = `<table class="dg-table"><thead><tr><th>Porty</th><th>Proces</th><th>Projekt</th><th>PID</th><th>Typ</th><th></th></tr></thead><tbody>${r.rows.map((x, i) => `<tr style="animation-delay:${Math.min(i, 12) * 22}ms">
            <td>${x.ports.map((p) => `<a class="port-tag" href="http://localhost:${p.port}/" target="_blank" rel="noopener" title="Otwórz http://localhost:${p.port}/">${p.port}</a>`).join('')}${x.ports.some((p) => p.public) && x.kind !== 'xampp' ? '<span class="pub" title="Dostępny z sieci lokalnej (nasłuchuje na wszystkich interfejsach)"><i class="fa-solid fa-globe"></i> sieć</span>' : ''}</td>
            <td><b style="color:var(--text-bright);font-weight:500">${e(x.label)}</b>${x.cmd ? `<div class="mono" style="color:var(--text-muted);max-width:420px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${e(x.cmd)}">${e(x.cmd)}</div>` : ''}</td>
            <td>${x.project ? `<a href="javascript:void(0)" data-open="${e(x.project)}" style="color:#f0b84a">${e(x.project)}</a>` : '<span style="color:var(--text-muted)">-</span>'}</td>
            <td class="mono">${x.pid}</td><td><span class="kind ${x.kind}">${kindLbl[x.kind]}</span></td>
            <td style="text-align:right">${x.killable ? `<button class="dg-btn sm danger" data-kill="${i}"><i class="fa-solid fa-power-off"></i> Zakończ</button>` : `<span style="color:var(--text-muted);font-size:11px" title="${e(x.why)}">chroniony</span>`}</td></tr>`).join('')}</tbody></table>`;
        box.onclick = (ev) => {
            const k = ev.target.closest('[data-kill]');
            if (k) return killProc(window.__dgPorts[+k.dataset.kill]);
            const o = ev.target.closest('[data-open]');
            if (o && typeof showProjectDetails === 'function') showProjectDetails(DOCROOT + '\\' + o.dataset.open, o.dataset.open);
        };
    }

    function killProc(x) {
        const ports = x.ports.map((p) => p.port).join(', ');
        const warn = x.kind === 'dev' ? '' : '<br><br><span style="color:#da3633">To nie wygląda na serwer deweloperski. Upewnij się, że możesz zamknąć ten program.</span>';
        showCustomConfirm(`Zakończyć proces <strong>${e(x.label)}</strong> (PID ${x.pid}), który nasłuchuje na porcie ${e(ports)}?${x.exe ? `<br><small style="color:var(--text-muted)">${e(x.exe)}</small>` : ''}${warn}`, async () => {
            const r = await tcall({ action: 'ports_kill', pid: x.pid });
            if (r.status === 'ok') { showToast('Zakończono proces ' + x.label, 'success'); renderPorts(); }
            else showToast(r.msg || 'Nie udało się zakończyć procesu', 'error');
        }, 'Zakończ');
    }

    /* ---------- BAZY DANYCH ---------- */
    async function renderDbs() {
        $('#dgBody').innerHTML = `<div class="dg-note"><i class="fa-solid fa-lightbulb"></i> <b>Zrzut SQL</b> to pełna kopia bazy w jednym pliku. Zrób ją <b>przed</b> zmianą struktury bazy przez AI: gdy coś pójdzie źle, wczytasz ją w phpMyAdmin (Import). Pliki lądują poza <code>htdocs</code>, więc nie są dostępne z przeglądarki.</div>
            <div class="dg-bar"><button class="dg-btn" id="dgDRef"><i class="fa-solid fa-rotate"></i> Odśwież</button><span id="dgDInfo" style="margin-left:auto;font-size:12px;color:var(--text-muted)"></span></div>
            <div id="dgDbs"><div class="dg-spin"><i class="fa-solid fa-spinner fa-spin"></i> Łączenie z MariaDB…</div></div>`;
        $('#dgDRef').onclick = renderDbs;
        const r = await tcall({ action: 'mysql_dbs' });
        const box = $('#dgDbs');
        if (!box) return;
        if (r.status !== 'ok') {
            box.innerHTML = `<div class="dg-empty"><i class="fa-solid fa-database"></i><b>${e(r.msg || 'Brak połączenia z MariaDB.')}</b><br><br><button class="dg-btn primary" id="dgStart"><i class="fa-solid fa-play"></i> Uruchom MariaDB</button></div>`;
            $('#dgStart').onclick = async () => { await window.diagStartMysql(); renderDbs(); };
            return;
        }
        $('#dgDInfo').textContent = `${r.dbs.length} baz · zrzuty w: ${r.dir}`;
        box.innerHTML = `<div class="dg-cols">
            <div class="dg-card"><h3>Bazy danych</h3>${r.dbs.length ? `<table class="dg-table" style="border:none"><thead><tr><th>Baza</th><th>Tabele</th><th>Rozmiar</th><th></th></tr></thead><tbody>${r.dbs.map((d, i) => `<tr style="animation-delay:${Math.min(i, 12) * 20}ms"><td><b style="color:var(--text-bright);font-weight:500">${e(d.name)}</b></td><td class="mono">${d.tables}</td><td class="mono">${fmtBytes(d.size)}</td><td style="text-align:right"><button class="dg-btn sm primary" data-dump="${e(d.name)}"><i class="fa-solid fa-file-export"></i> Zrzuć SQL</button></td></tr>`).join('')}</tbody></table>` : '<div class="dg-empty">Brak własnych baz.</div>'}</div>
            <div class="dg-card"><h3>Ostatnie zrzuty <button class="dg-btn sm" id="dgOpenDir" style="float:right;margin-top:-4px"><i class="fa-solid fa-folder-open"></i> Otwórz folder</button></h3>${r.dumps.length ? `<table class="dg-table" style="border:none"><tbody>${r.dumps.map((d) => `<tr><td class="mono" style="word-break:break-all">${e(d.file)}<div style="color:var(--text-muted)">${fmtBytes(d.size)} · ${ago(d.time)}</div></td><td style="text-align:right"><button class="dg-btn sm" data-dl="${e(d.file)}" title="Pobierz plik"><i class="fa-solid fa-download"></i></button></td></tr>`).join('')}</tbody></table>` : '<div class="dg-empty"><i class="fa-regular fa-file"></i>Nie ma jeszcze żadnych zrzutów.</div>'}</div></div>`;
        box.onclick = async (ev) => {
            const d = ev.target.closest('[data-dump]');
            if (d) {
                d.disabled = true;
                d.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Zrzucam…';
                const res = await tcall({ action: 'mysql_dump', db: d.dataset.dump });
                if (res.status === 'ok') { showToast(`Zrzut „${d.dataset.dump}”: ${fmtBytes(res.size)}`, 'success'); renderDbs(); }
                else { showToast(res.msg || 'Zrzut nie powiódł się', 'error'); d.disabled = false; d.innerHTML = '<i class="fa-solid fa-file-export"></i> Zrzuć SQL'; }
                return;
            }
            const dl = ev.target.closest('[data-dl]');
            if (dl) {
                const f = document.createElement('form');
                f.method = 'POST'; f.action = 'mrprompt_server/tools_handler.php'; f.target = '_blank';
                [['action', 'dump_download'], ['file', dl.dataset.dl], ['_token', window.MRP_TOKEN]].forEach(([n, v]) => { const i = document.createElement('input'); i.type = 'hidden'; i.name = n; i.value = v; f.appendChild(i); });
                document.body.appendChild(f); f.submit(); f.remove();
                return;
            }
            if (ev.target.closest('#dgOpenDir')) { const res = await tcall({ action: 'open_backup_dir' }); if (res.status !== 'ok') showToast(res.msg || 'Nie udało się otworzyć folderu', 'error'); }
        };
    }

    window.diagStartMysql = async function () {
        showToast('Uruchamiam MariaDB… (do 10 s)', 'info');
        const r = await tcall({ action: 'mysql_start' });
        showToast(r.msg || (r.status === 'ok' ? 'Gotowe' : 'Nie udało się'), r.status === 'ok' ? 'success' : 'error');
        if (typeof pollStatus === 'function') pollStatus();
    };

    /* =====================================================================
     *  „CZY PROJEKT DZIAŁA?”
     * ===================================================================== */
    const ICON = { ok: 'fa-solid fa-circle-check', warn: 'fa-solid fa-triangle-exclamation', fail: 'fa-solid fa-circle-xmark' };
    window.diagHealth = async function (path, name) {
        name = name || baseName(path);
        modal(mHead('fa-solid fa-heart-pulse', 'Czy projekt „' + e(name) + '” działa?') + '<div class="dgm-body"><div class="dg-spin"><i class="fa-solid fa-spinner fa-spin"></i><br>Sprawdzam składnię PHP, odpowiedź strony, pliki i logi…<br><small>Zwykle 2 do 10 sekund</small></div></div>', false);
        const r = await tcall({ action: 'health', path });
        if (r.status !== 'ok') { modal(mHead('fa-solid fa-heart-pulse', 'Kontrola projektu') + `<div class="dgm-body"><div class="verdict fail"><i class="fa-solid fa-circle-xmark"></i>${e(r.msg || 'Błąd')}</div></div>`); return; }
        const v = { ok: ['ok', 'fa-solid fa-circle-check', 'Wszystko wygląda dobrze'], warn: ['warn', 'fa-solid fa-triangle-exclamation', 'Działa, ale są uwagi'], fail: ['fail', 'fa-solid fa-circle-xmark', 'Wykryto problemy'] }[r.overall];
        const report = `Kontrola projektu ${r.name} (XAMPP, Windows), wynik: ${({ ok: 'OK', warn: 'UWAGI', fail: 'BŁĘDY' })[r.overall]}\n\n` + r.checks.map((c) => `[${({ ok: 'OK', warn: 'UWAGA', fail: 'BŁĄD' })[c.status]}] ${c.title}: ${c.detail}` + (c.items ? '\n' + c.items.map((i) => '   - ' + i).join('\n') : '')).join('\n') + '\n\nPopraw wykryte błędy. Nie zmieniaj niczego poza tym, co jest potrzebne.';
        const box = modal(mHead('fa-solid fa-heart-pulse', 'Czy projekt „' + e(r.name) + '” działa?') + `<div class="dgm-body">
            <div class="verdict ${v[0]}"><i class="${v[1]}"></i>${v[2]}</div>
            ${r.checks.map((c, i) => `<div class="chk ${c.status}" style="animation-delay:${i * 45}ms"><i class="${ICON[c.status]}"></i><div><b>${e(c.title)}</b><div class="d">${e(c.detail)}</div>${c.items ? '<ul>' + c.items.map((x) => `<li>${e(x)}</li>`).join('') + '</ul>' : ''}</div></div>`).join('')}
        </div><div class="dgm-foot">
            <button class="dg-btn" id="hcLogs"><i class="fa-solid fa-bug"></i> Logi projektu</button>
            <button class="dg-btn" id="hcOpen"><i class="fa-solid fa-up-right-from-square"></i> Otwórz stronę</button>
            <button class="dg-btn" id="hcAgain"><i class="fa-solid fa-rotate"></i> Sprawdź ponownie</button>
            <button class="dg-btn primary" id="hcCopy"><i class="fa-solid fa-copy"></i> Kopiuj raport dla AI</button></div>`, false);
        $('#hcCopy', box).onclick = () => copyText(report, 'Skopiowano raport dla AI');
        $('#hcOpen', box).onclick = () => window.open(r.url, '_blank', 'noopener');
        $('#hcAgain', box).onclick = () => window.diagHealth(path, name);
        $('#hcLogs', box).onclick = () => { closeM(); openDiag('logs', { project: r.name }); };
    };

    /* =====================================================================
     *  AUDYT PRZED PUBLIKACJĄ
     * ===================================================================== */
    window.diagAudit = async function (path, name) {
        name = name || baseName(path);
        modal(mHead('fa-solid fa-shield-halved', 'Audyt projektu „' + e(name) + '”') + '<div class="dgm-body"><div class="dg-spin"><i class="fa-solid fa-spinner fa-spin"></i><br>Przeglądam pliki projektu…</div></div>', true);
        const r = await tcall({ action: 'audit', path });
        if (r.status !== 'ok') { modal(mHead('fa-solid fa-shield-halved', 'Audyt projektu') + `<div class="dgm-body"><div class="verdict fail"><i class="fa-solid fa-circle-xmark"></i>${e(r.msg || 'Błąd')}</div></div>`); return; }
        const total = r.counts.high + r.counts.med + r.counts.low;
        const sevL = { high: 'poważne', med: 'średnie', low: 'drobne' };
        const cls = r.counts.high ? 'fail' : total ? 'warn' : 'ok';
        const report = `Audyt bezpieczeństwa przed publikacją projektu ${r.name} (PHP, XAMPP). Znaleziono: poważne ${r.counts.high}, średnie ${r.counts.med}, drobne ${r.counts.low}.\n\n` + r.findings.map((f) => `[${sevL[f.sev].toUpperCase()}] ${f.title}\n${f.detail}\n` + f.files.map((x) => '   - ' + x).join('\n') + (f.total > f.files.length ? `\n   … i ${f.total - f.files.length} więcej` : '')).join('\n\n') + '\n\nPopraw te problemy tak, aby projekt był bezpieczny do wgrania na hosting. Sekrety przenieś poza katalog publiczny. Nie zmieniaj działania aplikacji.';
        const box = modal(mHead('fa-solid fa-shield-halved', 'Audyt projektu „' + e(r.name) + '”') + `<div class="dgm-body">
            <div class="verdict ${cls}"><i class="${total ? (r.counts.high ? 'fa-solid fa-circle-xmark' : 'fa-solid fa-triangle-exclamation') : 'fa-solid fa-circle-check'}"></i>
                <div>${total ? `Znaleziono ${total} ${total === 1 ? 'problem' : (total % 10 >= 2 && total % 10 <= 4 && (total % 100 < 12 || total % 100 > 14) ? 'problemy' : 'problemów')}: poważne ${r.counts.high}, średnie ${r.counts.med}, drobne ${r.counts.low}` : 'Nie znaleziono typowych problemów'}<div style="font-weight:400;font-size:12px;opacity:.8">Przejrzano ${r.scanned} plików. To szybki przegląd typowych błędów, nie pełny test bezpieczeństwa.</div></div></div>
            ${r.findings.map((f, i) => `<div class="aud" style="animation-delay:${i * 50}ms"><div class="aud-h"><span class="sev ${f.sev}">${sevL[f.sev]}</span>${e(f.title)}<span style="margin-left:auto;color:var(--text-muted);font-size:11px">${f.total}</span></div>
                <div class="aud-b">${e(f.detail)}<ul>${f.files.map((x) => `<li>${e(x)}</li>`).join('')}${f.total > f.files.length ? `<li style="color:var(--text-muted)">… i ${f.total - f.files.length} więcej</li>` : ''}</ul></div></div>`).join('') || '<div class="dg-empty"><i class="fa-regular fa-face-smile"></i>Czysto. Pamiętaj tylko, żeby nie wgrywać plików <code>.env</code> i folderu <code>.git</code>.</div>'}
            <details style="margin-top:10px"><summary style="cursor:pointer;color:var(--text-muted);font-size:12px">Przykładowa ochrona w pliku .htaccess (dla hostingu Apache)</summary><pre class="lg-detail" style="max-height:none">${e(r.htaccess)}</pre></details>
        </div><div class="dgm-foot"><button class="dg-btn" id="auAgain"><i class="fa-solid fa-rotate"></i> Skanuj ponownie</button><button class="dg-btn primary" id="auCopy" ${total ? '' : 'disabled'}><i class="fa-solid fa-copy"></i> Kopiuj raport dla AI</button></div>`, true);
        $('#auCopy', box).onclick = () => copyText(report, 'Skopiowano raport audytu dla AI');
        $('#auAgain', box).onclick = () => window.diagAudit(path, name);
    };

    window.diagLogsFor = (name) => openDiag('logs', { project: name });

    /* =====================================================================
     *  KREATOR NOWEGO PROJEKTU
     * ===================================================================== */
    const TPL = [
        { id: 'php', icon: 'fa-brands fa-php', t: 'PHP', d: 'index.php + styl. Dobry start dla większości aplikacji.' },
        { id: 'php_sqlite', icon: 'fa-solid fa-database', t: 'PHP + SQLite', d: 'Lista wpisów z bazą w pliku (chroniona przed pobraniem).' },
        { id: 'html', icon: 'fa-brands fa-html5', t: 'Strona HTML', d: 'HTML, CSS i JavaScript, bez PHP.' },
        { id: 'empty', icon: 'fa-regular fa-folder', t: 'Pusty', d: 'Tylko README, zasady dla AI i git.' },
    ];
    window.diagNewProject = function () {
        let tpl = 'php';
        const box = modal(mHead('fa-solid fa-wand-magic-sparkles', 'Nowy projekt') + `<div class="dgm-body">
            <label class="dg-label" style="margin-top:0">Nazwa projektu (folder w htdocs)</label>
            <input class="dg-field" id="npName" placeholder="np. MojaAplikacja" autocomplete="off" spellcheck="false" maxlength="60">
            <label class="dg-label">Krótki opis (pojawi się w panelu i w README)</label>
            <input class="dg-field" id="npDesc" placeholder="np. Lista zadań dla mojego zespołu" autocomplete="off" maxlength="200">
            <label class="dg-label">Szablon</label>
            <div class="tpl-grid" id="npTpl">${TPL.map((x) => `<div class="tpl ${x.id === tpl ? 'on' : ''}" data-t="${x.id}"><b><i class="${x.icon}"></i>${x.t}</b>${x.d}</div>`).join('')}</div>
            <label class="dg-check"><input type="checkbox" id="npAi" checked><span><b>Dodaj CLAUDE.md</b><small>Zasady dla AI: styl kodu, ścieżki względne, polskie komunikaty, testy po zmianach.</small></span></label>
            <label class="dg-check"><input type="checkbox" id="npGit" checked><span><b>Utwórz repozytorium git z pierwszym zapisem</b><small>Punkt powrotu, dzięki któremu zobaczysz w Edytorze, co zmieniło AI.</small></span></label>
            <div class="dg-err" id="npErr"></div>
        </div><div class="dgm-foot"><button class="dg-btn" id="npCancel">Anuluj</button><button class="dg-btn primary" id="npGo"><i class="fa-solid fa-wand-magic-sparkles"></i> Utwórz projekt</button></div>`, false);
        $('#npCancel', box).onclick = closeM;
        $('#npTpl', box).onclick = (ev) => { const t = ev.target.closest('.tpl'); if (!t) return; tpl = t.dataset.t; box.querySelectorAll('.tpl').forEach((x) => x.classList.toggle('on', x === t)); };
        setTimeout(() => $('#npName', box).focus(), 40);
        const go = async () => {
            const name = $('#npName', box).value.trim();
            const err = $('#npErr', box);
            if (!name) { err.textContent = 'Podaj nazwę projektu.'; return; }
            $('#npGo', box).disabled = true;
            $('#npGo', box).innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Tworzę…';
            const r = await tcall({ action: 'new_project', name, desc: $('#npDesc', box).value, template: tpl, ai_files: $('#npAi', box).checked ? 1 : '', git: $('#npGit', box).checked ? 1 : '' });
            if (r.status !== 'ok') { err.textContent = r.msg || 'Nie udało się utworzyć projektu.'; $('#npGo', box).disabled = false; $('#npGo', box).innerHTML = '<i class="fa-solid fa-wand-magic-sparkles"></i> Utwórz projekt'; return; }
            if (typeof refreshFolderInTree === 'function') refreshFolderInTree(DOCROOT);
            const b2 = modal(mHead('fa-solid fa-circle-check', 'Projekt „' + e(r.name) + '” jest gotowy') + `<div class="dgm-body">
                <div class="verdict ok"><i class="fa-solid fa-circle-check"></i>Utworzono folder i ${r.files.length} plików${r.git === true ? ', repozytorium git z pierwszym zapisem' : ''}.</div>
                ${r.git === false ? `<div class="dg-note" style="border-color:var(--red)">Git nie został zainicjowany: ${e(r.git_msg || 'sprawdź, czy git jest zainstalowany')}. Możesz to zrobić później w Edytorze (Przegląd zmian).</div>` : ''}
                <div class="lg-detail" style="max-height:none">${r.files.map((f) => e(f)).join('\n')}</div>
                <p style="color:var(--text-muted);font-size:12.5px;margin:12px 0 0">Adres lokalny: <a href="${e(r.url)}" target="_blank" rel="noopener" style="color:#f0b84a">${e(r.url)}</a></p>
            </div><div class="dgm-foot"><button class="dg-btn" id="npClose">Zamknij</button><button class="dg-btn" id="npWeb"><i class="fa-solid fa-globe"></i> Otwórz w przeglądarce</button><button class="dg-btn primary" id="npEd"><i class="fa-solid fa-code"></i> Otwórz w Edytorze</button></div>`, false);
            $('#npClose', b2).onclick = closeM;
            $('#npWeb', b2).onclick = () => window.open(r.url, '_blank', 'noopener');
            $('#npEd', b2).onclick = () => { window.open('mrprompt_server/edytor/index.php?project=' + encodeURIComponent(r.path), '_blank'); closeM(); };
        };
        $('#npGo', box).onclick = go;
        box.addEventListener('keydown', (ev) => { if (ev.key === 'Enter' && ev.target.tagName === 'INPUT' && ev.target.type === 'text') go(); });
    };

    /* ---------- karta projektu: zakładka „Diagnostyka” ---------- */
    document.addEventListener('click', (ev) => {
        const b = ev.target.closest('[data-diag]');
        if (!b) return;
        const path = (typeof currentPath !== 'undefined' && currentPath) || '';
        if (!path) return;
        const name = baseName(path);
        if (b.dataset.diag === 'health') window.diagHealth(path, name);
        else if (b.dataset.diag === 'audit') window.diagAudit(path, name);
        else if (b.dataset.diag === 'logs') window.diagLogsFor(name);
    });
})();
