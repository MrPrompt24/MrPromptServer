<?php
require_once 'mrprompt_server/security.php';
require_once 'mrprompt_server/apps_handler.php';
require_once 'mrprompt_server/project_handler.php';
require_once 'mrprompt_server/tree_render.php';

$apps = getApps($db);
$favPaths = [];
try {
    $favPaths = $db->query("SELECT path FROM favorites")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
}
?>
<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= mrp_client_script() ?>
    <title>MrPrompt Server 4.0</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="mrprompt_server/favicon.ico">
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Keania One hostowana lokalnie (licencja OFL): logo i loader działają też bez internetu */
        @font-face { font-family: 'Keania One'; font-style: normal; font-weight: 400; font-display: block; src: url('mrprompt_server/fonts/KeaniaOne-ext.woff2') format('woff2'); unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF; }
        @font-face { font-family: 'Keania One'; font-style: normal; font-weight: 400; font-display: block; src: url('mrprompt_server/fonts/KeaniaOne.woff2') format('woff2'); unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD; }
        :root {
            --bg-dark: #1e1e1e;
            --sidebar-bg: #252526;
            --accent: #b87a00;
            --accent-hover: #d18f0a;
            --accent-dim: rgba(184, 122, 0, 0.12);
            --text-main: #cccccc;
            --text-muted: #858585;
            --text-bright: #ffffff;
            --border: #3d3d3d;
            --border-light: #454545;
            --taskbar-h: 28px;
            --item-hover: #2a2d2e;
            --item-active: #37373d;
            --header-bg: #323233;
            --card-bg: #252526;
            --input-bg: #3c3c3c;
            --green: #4ec994;
            --yellow: #ffcc02;
            --red: #f44747;
            --purple: #c586c0;
            --orange: #ce9178;
        }

        /* --- SCROLLBARS --- */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: #454545;
            border-radius: 0;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #6a6a6a;
        }

        * {
            scrollbar-width: thin;
            scrollbar-color: #454545 transparent;
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Sora', sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-main);
            display: flex;
            height: 100vh;
            overflow: hidden;
            font-size: 13px;
        }

        /* --- SIDEBAR --- */
        aside {
            width: 280px;
            min-width: 280px;
            background-color: var(--sidebar-bg);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            height: calc(100vh - var(--taskbar-h));
            transition: width 0.3s ease, min-width 0.3s ease;
        }

        aside.full-view-mode {
            width: 80%;
            min-width: 650px;
        }

        .sidebar-header {
            padding: 12px 12px 10px;
            border-bottom: 1px solid var(--border);
            flex-shrink: 0;
            background: var(--sidebar-bg);
        }

        .main-logo {
            max-height: 28px;
            display: block;
            filter: brightness(0.9);
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .toggle-full-view {
            color: var(--text-muted);
            cursor: pointer;
            padding: 4px 8px;
            font-size: 14px;
            border-radius: 3px;
            transition: background 0.15s, color 0.15s;
        }

        .toggle-full-view:hover {
            color: var(--text-main);
            background: rgba(255, 255, 255, 0.1);
        }

        .search-box {
            width: 100%;
            background: var(--input-bg);
            border: 1px solid transparent;
            color: var(--text-main);
            padding: 6px 10px 6px 28px;
            font-family: 'Sora', sans-serif;
            font-size: 12px;
            border-radius: 2px;
            outline: none;
            transition: border-color 0.15s;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23858585' stroke-width='2'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cline x1='21' y1='21' x2='16.65' y2='16.65'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: 8px center;
        }

        .search-box:focus {
            border-color: var(--accent);
            background-color: #2d2d2d;
        }

        .search-box::placeholder {
            color: #606060;
        }

        .filter-bar {
            display: flex;
            gap: 0;
            margin-top: 10px;
            font-size: 11px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 0;
        }

        .filter-link {
            color: var(--text-muted);
            cursor: pointer;
            text-decoration: none;
            padding: 4px 10px 6px;
            border-bottom: 2px solid transparent;
            transition: color 0.15s, border-color 0.15s;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 10px;
            font-weight: 600;
        }

        .filter-link:hover {
            color: var(--text-main);
        }

        .filter-link.active {
            color: var(--text-main);
            border-bottom-color: var(--accent);
        }

        #file-tree-container {
            flex: 1;
            overflow-y: auto;
            padding: 4px 0;
        }

        /* --- TREE --- */
        ul {
            list-style: none;
            padding-left: 0;
            margin: 0;
        }

        #file-tree-container>ul {
            padding-left: 0;
        }

        #file-tree-container ul ul {
            padding-left: 12px;
        }

        .tree-item {
            cursor: pointer;
            padding: 3px 8px 3px 6px;
            border-radius: 0;
            display: flex;
            align-items: center;
            /* keep vertical alignment initially centered */
            gap: 4px;
            transition: background 0.1s;
            white-space: normal;
            word-break: break-word;
            min-height: 24px;
            position: relative;
        }

        .tree-item:hover {
            background: var(--item-hover);
        }

        .tree-item a {
            text-decoration: none;
            color: inherit;
            flex: 1;
            font-size: 12.5px;
        }

        .item-name-wrap {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 4px;
            min-width: 0;
        }

        aside.full-view-mode .item-name-wrap {
            flex: 1 1 250px;
        }

        .folder-toggle {
            color: #858585;
            font-size: 7px;
            width: 14px;
            cursor: pointer;
            text-align: center;
            flex-shrink: 0;
            transition: transform 0.15s;
        }

        .folder-icon {
            color: #c8a96e;
            font-size: 13px;
        }

        /* --- NESTED TREE ITEMS --- */
        .nested .tree-item {
            color: #04b541 !important;
        }

        .nested .tree-item .icon,
        .nested .tree-item .folder-icon {
            color: #04b541 !important;
        }

        .nested .tree-item .folder-icon {
            color: #c8a96e !important;
        }

        .nested .tree-item a,
        .nested .tree-item strong {
            color: #04b541 !important;
            font-size: 12px;
            font-weight: 400;
        }

        .github-icon {
            color: #cccccc;
            font-size: 13px;
            margin-left: 4px;
            flex-shrink: 0;
            transition: color 0.15s, transform 0.15s;
        }

        .github-icon:hover {
            color: var(--accent);
            transform: scale(1.15);
        }

        .fav-star {
            margin-left: auto;
            color: transparent;
            font-size: 12px;
            transition: color 0.15s, transform 0.15s;
            flex-shrink: 0;
            line-height: 1;
        }

        .tree-item:hover .fav-star {
            color: #555;
        }

        .fav-star.active {
            color: var(--yellow) !important;
        }

        .fav-star:hover {
            transform: scale(1.2);
        }

        .info-badge {
            background: var(--accent);
            color: white;
            padding: 1px 5px;
            border-radius: 2px;
            font-size: 9px;
            margin-left: 4px;
            font-family: 'JetBrains Mono', monospace;
            letter-spacing: 0;
            cursor: help;
            font-weight: 600;
            flex-shrink: 0;
        }

        aside.full-view-mode .info-badge {
            display: none;
        }

        .full-view-columns {
            display: none;
            flex: 2;
            margin-left: 10px;
            gap: 15px;
            font-size: 11px;
            color: #aaa;
            align-items: center;
            min-width: 0;
            margin-right: auto;
        }

        aside.full-view-mode .full-view-columns {
            display: flex;
        }

        .full-view-desc {
            flex: 1;
            border-left: 1px solid var(--border);
            padding-left: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.4;
            color: var(--yellow);
        }

        .full-view-date {
            flex: 0 0 auto;
            color: #888;
            border-left: 1px solid var(--border);
            padding-left: 15px;
            text-align: right;
            white-space: nowrap;
        }

        .nested {
            display: none;
        }

        .nested.active {
            display: block;
        }

        /* --- GLOBAL TOOLTIP --- */
        #global-tooltip {
            position: fixed;
            background: #3c3f41;
            border: 1px solid #555;
            color: var(--text-main);
            padding: 6px 10px;
            border-radius: 3px;
            white-space: nowrap;
            z-index: 9999;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.5);
            pointer-events: none;
            font-size: 12px;
            display: none;
            font-family: 'JetBrains Mono', monospace;
        }

        /* --- MODAL --- */
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
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-box {
            background: #2d2d30;
            border: 1px solid #454545;
            padding: 32px;
            border-radius: 4px;
            max-width: 480px;
            width: 90%;
            text-align: center;
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.6);
            position: relative;
        }

        .close-modal {
            position: absolute;
            top: 12px;
            right: 16px;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 18px;
            line-height: 1;
            transition: color 0.15s;
            font-weight: 300;
        }

        .close-modal:hover {
            color: white;
        }

        /* --- STATS DASHBOARD (first definition) --- */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1px;
            width: 100%;
            max-width: 1000px;
            border: 1px solid var(--border);
            border-radius: 3px;
            overflow: hidden;
            background: var(--border);
        }

        .stat-card {
            background: var(--card-bg);
            padding: 20px;
        }

        .stat-title {
            color: var(--text-muted);
            font-size: 10px;
            margin-bottom: 6px;
            display: flex;
            justify-content: space-between;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        .stat-value {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text-bright);
            margin-bottom: 4px;
            font-family: 'JetBrains Mono', monospace;
        }

        .stat-desc {
            font-size: 11px;
            color: var(--text-muted);
        }

        .progress-bar {
            background: #3a3a3a;
            height: 3px;
            border-radius: 0;
            overflow: hidden;
            margin-top: 10px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--accent), var(--accent-hover));
            width: 0%;
            transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .stat-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .stat-list li {
            padding: 8px 0;
            border-bottom: 1px solid var(--border);
            font-size: 12px;
            display: flex;
            justify-content: space-between;
        }

        .stat-list li:last-child {
            border-bottom: none;
        }

        .tag {
            background: var(--item-active);
            padding: 2px 6px;
            border-radius: 2px;
            font-size: 10px;
            border: 1px solid var(--border);
            font-family: 'JetBrains Mono', monospace;
        }

        /* --- TABS --- */
        .tabs {
            display: flex;
            border-bottom: 1px solid var(--border);
            margin-bottom: 20px;
            background: #2d2d30;
            margin-left: -40px;
            margin-right: -40px;
            padding: 0 40px;
        }

        .tab-btn {
            background: none;
            border: none;
            color: var(--text-muted);
            padding: 10px 18px;
            cursor: pointer;
            font-family: 'Sora', sans-serif;
            font-size: 12px;
            border-bottom: 2px solid transparent;
            transition: color 0.15s, border-color 0.15s;
            text-transform: none;
            letter-spacing: 0;
        }

        .tab-btn:hover {
            color: var(--text-main);
            background: rgba(255, 255, 255, 0.04);
        }

        .tab-btn.active {
            color: var(--text-bright);
            border-bottom-color: var(--accent);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* --- MARKDOWN EDITOR --- */
        .md-editor-container {
            display: flex;
            gap: 1px;
            height: 500px;
            background: var(--border);
            border: 1px solid var(--border);
            border-radius: 2px;
            overflow: hidden;
        }

        .md-input {
            width: 50%;
            height: 100%;
            background: #1e1e1e;
            color: #d4d4d4;
            border: none;
            padding: 16px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            resize: none;
            line-height: 1.6;
            outline: none;
        }

        .md-preview {
            width: 50%;
            height: 100%;
            overflow-y: auto;
            padding: 16px;
            background: #252526;
            border: none;
        }

        .md-preview h1,
        .md-preview h2,
        .md-preview h3 {
            color: var(--accent);
            border-bottom: 1px solid var(--border);
            padding-bottom: 6px;
            font-family: 'Sora', sans-serif;
        }

        .md-preview p {
            line-height: 1.7;
        }

        .md-preview code {
            background: #1e1e1e;
            padding: 2px 6px;
            border-radius: 2px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            color: var(--orange);
        }

        .md-preview pre {
            background: #1e1e1e;
            padding: 14px;
            border-radius: 2px;
            overflow-x: auto;
            border-left: 3px solid var(--accent);
        }

        /* --- TODO LIST --- */
        .todo-item {
            display: flex;
            align-items: center;
            background: #2d2d30;
            padding: 10px 14px;
            margin-bottom: 2px;
            border-radius: 2px;
            border: 1px solid transparent;
            transition: border-color 0.15s, background 0.15s;
        }

        .todo-item:hover {
            border-color: var(--border-light);
            background: var(--item-hover);
        }

        .todo-item.done span {
            text-decoration: line-through;
            color: #555;
        }

        .todo-checkbox {
            margin-right: 14px;
            appearance: none;
            width: 16px;
            height: 16px;
            border: 1.5px solid #555;
            border-radius: 2px;
            cursor: pointer;
            display: grid;
            place-content: center;
            flex-shrink: 0;
            transition: border-color 0.15s, background 0.15s;
        }

        .todo-checkbox:hover {
            border-color: var(--accent);
        }

        .todo-checkbox::before {
            content: "";
            width: 8px;
            height: 8px;
            transform: scale(0);
            transition: 120ms transform ease-in-out;
            box-shadow: inset 1em 1em var(--accent);
            transform-origin: center;
            clip-path: polygon(14% 44%, 0 65%, 50% 100%, 100% 16%, 80% 0%, 43% 62%);
        }

        .todo-checkbox:checked {
            border-color: var(--accent);
            background: var(--accent-dim);
        }

        .todo-checkbox:checked::before {
            transform: scale(1);
        }

        /* --- STATS DASHBOARD (second, overrides) --- */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1px;
            margin-bottom: 1px;
            background: var(--border);
        }

        .stats-card {
            background: var(--card-bg);
            padding: 18px;
        }

        .stats-card h3 {
            margin: 0 0 8px 0;
            font-size: 10px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        .stats-big {
            font-size: 1.7rem;
            font-weight: 700;
            color: white;
            font-family: 'JetBrains Mono', monospace;
        }

        .stats-sub {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .stats-accordion {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 2px;
            margin-bottom: 12px;
            overflow: hidden;
        }

        .stats-accordion-header {
            padding: 11px 16px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #2d2d30;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-main);
            transition: background 0.15s;
        }

        .stats-accordion-header:hover {
            background: var(--item-active);
        }

        .stats-accordion-body {
            padding: 16px;
            display: none;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            font-size: 12px;
        }

        .stats-accordion.active .stats-accordion-body {
            display: grid;
        }

        .status-badge {
            display: inline-block;
            width: 7px;
            height: 7px;
            border-radius: 50%;
            margin-right: 5px;
        }

        .status-ok {
            background: var(--green);
            box-shadow: 0 0 6px var(--green);
        }

        .status-err {
            background: var(--red);
            box-shadow: 0 0 6px var(--red);
        }

        .system-row {
            display: flex;
            gap: 20px;
            font-size: 12px;
            color: var(--text-muted);
            padding: 12px 16px;
            background: var(--card-bg);
            border-radius: 2px;
            justify-content: space-around;
        }

        /* --- MAIN --- */
        main {
            flex: 1;
            display: flex;
            flex-direction: column;
            height: calc(100vh - var(--taskbar-h));
            background: var(--bg-dark);
        }

        header {
            height: 48px;
            padding: 0 20px;
            background: var(--header-bg);
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }

        .nav-menu {
            display: flex;
            gap: 0;
        }

        .nav-link {
            color: #cccccc;
            text-decoration: none;
            font-size: 12px;
            padding: 0 12px;
            height: 48px;
            display: flex;
            align-items: center;
            border-bottom: 2px solid transparent;
            transition: background 0.12s, color 0.12s, border-color 0.12s;
            opacity: 0.85;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.07);
            color: white;
            opacity: 1;
        }

        .btn-refresh {
            background: transparent;
            color: #cccccc;
            border: 1px solid #555;
            padding: 5px 14px;
            border-radius: 2px;
            cursor: pointer;
            font-weight: 400;
            font-size: 12px;
            font-family: 'Sora', sans-serif;
            transition: background 0.15s, border-color 0.15s, color 0.15s;
        }

        .btn-refresh:hover {
            background: var(--accent-dim);
            border-color: var(--accent);
            color: var(--accent);
        }

        .sidebar-header .btn-refresh {
            width: 100%;
            margin-top: 8px;
            padding: 6px 10px;
        }

        .nav-menu {
            white-space: nowrap;
            min-width: 0;
            overflow-x: auto;
            scrollbar-width: none;
        }

        .nav-menu::-webkit-scrollbar {
            display: none;
        }

        .nav-link {
            padding: 0 10px;
            flex-shrink: 0;
        }

        .content-area {
            flex: 1;
            display: flex;
            justify-content: flex-start;
            align-items: center;
            flex-direction: column;
            overflow-y: auto;
            padding: 40px;
        }

        .project-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            padding: 40px;
            border-radius: 3px;
            width: 100%;
            max-width: 800px;
            text-align: left;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.25);
            margin: auto;
        }

        /* --- FORM INFO --- */
        .info-form input,
        .info-form textarea {
            width: 100%;
            background: var(--input-bg);
            border: 1px solid var(--border);
            color: var(--text-main);
            padding: 8px 12px;
            margin-bottom: 14px;
            border-radius: 2px;
            font-family: 'Sora', sans-serif;
            font-size: 13px;
            outline: none;
            transition: border-color 0.15s;
        }

        .info-form input:focus,
        .info-form textarea:focus {
            border-color: var(--accent);
            background: #3a3a3a;
        }

        .info-form label {
            display: block;
            margin-bottom: 4px;
            font-size: 11px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* --- NOTES SYSTEM --- */
        .note-item {
            background: #2d2d30;
            border: 1px solid var(--border);
            border-radius: 2px;
            margin-bottom: 2px;
            overflow: hidden;
            transition: border-color 0.15s;
        }

        .note-item:hover {
            border-color: #555;
        }

        .note-header {
            padding: 12px 16px;
            background: #2d2d30;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.12s;
        }

        .note-header:hover {
            background: var(--item-active);
        }

        .note-title {
            font-weight: 600;
            color: var(--text-main);
            font-size: 13px;
        }

        .note-meta {
            font-size: 11px;
            color: #555;
            margin-right: 12px;
            font-family: 'JetBrains Mono', monospace;
        }

        .note-actions {
            display: flex;
            gap: 6px;
        }

        .note-body {
            padding: 20px;
            display: none;
            border-top: 1px solid var(--border);
            background: #1e1e1e;
        }

        .note-body.active {
            display: block;
        }

        .note-body img {
            max-width: 100%;
            border-radius: 2px;
        }

        .btn-action {
            padding: 3px 10px;
            border-radius: 2px;
            border: 1px solid var(--border);
            background: transparent;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 11px;
            font-family: 'Sora', sans-serif;
            transition: background 0.15s, color 0.15s, border-color 0.15s;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .btn-action:hover {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
        }

        .btn-danger:hover {
            background: var(--red);
            border-color: var(--red);
        }

        /* --- TOAST --- */
        #toast-container {
            position: fixed;
            bottom: 60px;
            right: 16px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .toast {
            background: #b87a00;
            color: white;
            padding: 10px 16px;
            border-radius: 2px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.4);
            font-size: 12px;
            animation: slideIn 0.2s ease-out forwards;
            min-width: 240px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-left: 3px solid rgba(255, 255, 255, 0.3);
        }

        .toast.success {
            background: #1a3a2a;
            border-left-color: var(--green);
            color: var(--green);
        }

        .toast.error {
            background: #3a1a1a;
            border-left-color: var(--red);
            color: #ff8080;
        }

        .toast.info {
            background: #2e220a;
            border-left-color: var(--accent);
            color: #f0b84a;
        }

        @keyframes slideIn {
            from {
                transform: translateX(110%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* --- TASKBAR --- */
        .taskbar {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            height: var(--taskbar-h);
            background: #b87a00;
            border-top: none;
            display: flex;
            align-items: center;
            padding: 0 12px;
            z-index: 2000;
        }

        .start-button {
            background: rgba(0, 0, 0, 0.15);
            border: none;
            color: white;
            padding: 5px 14px;
            border-radius: 0;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-family: 'Sora', sans-serif;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.3px;
            transition: background 0.15s;
            height: 100%;
        }

        .start-button:hover {
            background: rgba(0, 0, 0, 0.25);
        }

        .start-menu {
            position: fixed;
            bottom: calc(var(--taskbar-h) + 6px);
            left: 6px;
            width: 380px;
            max-height: 480px;
            background: #2d2d30;
            border: 1px solid #454545;
            border-radius: 3px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.6);
            z-index: 1999;
            display: none;
            flex-direction: column;
            padding: 14px;
        }

        .start-menu.active {
            display: flex;
        }

        /* Welcome screen */
        .welcome-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1px;
            background: var(--border);
            border: 1px solid var(--border);
            border-radius: 3px;
            overflow: hidden;
            max-width: 640px;
            width: 100%;
        }

        .welcome-tile {
            background: var(--card-bg);
            padding: 20px 24px;
        }

        .welcome-tile h4 {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--text-muted);
            margin: 0 0 6px 0;
        }

        .welcome-tile p {
            font-size: 12px;
            color: var(--text-muted);
            margin: 0;
        }

        /* Active indicator in taskbar */
        .taskbar-spacer {
            flex: 1;
        }

        .taskbar-info {
            color: rgba(255, 255, 255, 0.8);
            font-size: 11px;
            font-family: 'JetBrains Mono', monospace;
            display: flex;
            gap: 16px;
            align-items: center;
        }

        /* Sidebar section label */
        .sidebar-section-label {
            padding: 8px 12px 4px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #cccccc;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Input focus ring style */
        input[type=text]:focus,
        input[type=date]:focus,
        textarea:focus {
            outline: 1px solid var(--accent);
            outline-offset: -1px;
        }

        /* Generic button style used inline */
        button[style*="background:var(--accent)"],
        button[style*="background: var(--accent)"] {
            font-family: 'Sora', sans-serif !important;
        }

        /* Tree strong text */
        .tree-item strong {
            font-size: 12.5px;
            font-weight: 400;
            color: #cccccc;
            cursor: pointer;
            word-break: break-word;
        }

        .tree-item strong:hover {
            color: white;
        }

        .icon {
            font-size: 12px;
            flex-shrink: 0;
        }

        /* File icon color */
        .tree-item:not(.nested .tree-item)>.icon {
            color: #858585;
        }

        /* Inline style button overrides for consistency */
        .modal-box button {
            font-family: 'Sora', sans-serif;
            font-size: 12px;
        }
        .app-input { width:100%; padding:7px 9px; margin-bottom:6px; background:var(--input-bg); border:1px solid var(--border); color:var(--text-main); border-radius:3px; font-size:12px; }
        .icon-picker { display:grid; grid-template-columns:repeat(8,1fr); gap:3px; margin:4px 0 8px; }
        .icon-opt { background:transparent; border:1px solid var(--border); color:var(--text-muted); border-radius:3px; height:28px; cursor:pointer; font-size:13px; transition:all .12s; }
        .icon-opt:hover { color:var(--text-main); background:var(--item-hover); }
        .icon-opt.selected { color:white; background:var(--accent); border-color:var(--accent); }
        .app-save { width:100%; background:var(--accent); color:white; border:none; padding:8px; border-radius:3px; cursor:pointer; font-weight:600; font-size:12px; }
        .app-save:hover { background:var(--accent-hover); }
        .trash-icon { color:#da3633; cursor:pointer; margin-left:5px; font-size:11px; opacity:0; transition:opacity .12s; }
        .tree-item:hover .trash-icon { opacity:.8; }
        .trash-icon:hover { opacity:1 !important; }
        .folder-toggle, .info-badge, .github-icon, .fav-star, [data-act] { cursor:pointer; }
        .stats-section { color:var(--text-muted); font-size:11px; text-transform:uppercase; letter-spacing:1px; margin:24px 0 10px; font-weight:600; }
        .stats-section small { text-transform:none; letter-spacing:0; font-weight:400; }
        .stats-card h3 i { margin-right:4px; }
        .stats-big { display:flex; align-items:center; gap:2px; word-break:break-all; }
        .lang-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:1px; background:var(--border); border:1px solid var(--border); margin-bottom:14px; }
        .lang-card { background:var(--card-bg); padding:14px 16px; display:flex; align-items:center; gap:12px; }
        .lang-card > i { font-size:26px; }
        .lang-n { font-size:1.3rem; font-weight:700; color:white; font-family:'JetBrains Mono',monospace; line-height:1.1; }
        .lang-l { font-size:11px; color:var(--text-muted); }
        .lang-s { margin-left:auto; font-size:11px; color:var(--text-muted); font-family:'JetBrains Mono',monospace; }
        .kv-body { grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); }
        .kv { display:flex; flex-direction:column; gap:2px; word-break:break-word; }
        .kv span { font-size:10px; text-transform:uppercase; letter-spacing:.8px; color:var(--text-muted); }
        .kv b { font-weight:500; color:var(--text-bright); }
        .ext-list { display:flex; flex-wrap:wrap; gap:4px; }
        .recent-box { background:var(--card-bg); border:1px solid var(--border); border-radius:2px; }
        .recent-row { padding:9px 14px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; gap:12px; font-size:12px; }
        .recent-row:last-child { border-bottom:none; }
        .recent-file { color:var(--accent); word-break:break-all; }
        .recent-time { color:var(--text-muted); font-family:'JetBrains Mono',monospace; white-space:nowrap; }
        /* --- LOADER STARTOWY --- */
        #app-loader {
            position: fixed;
            inset: 0;
            z-index: 10000;
            background: var(--bg-dark);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 18px;
            transition: opacity 0.45s ease, visibility 0.45s;
        }

        #app-loader.done {
            opacity: 0;
            visibility: hidden;
        }

        .loader-word {
            position: relative;
            font-family: 'Inter', 'Sora', sans-serif;
            font-size: clamp(44px, 9vw, 84px);
            font-weight: 700;
            letter-spacing: -1px;
            line-height: 1;
            color: rgba(255, 255, 255, 0.10);
            user-select: none;
        }

        .loader-word .fill {
            position: absolute;
            left: 0;
            top: 0;
            white-space: nowrap;
            overflow: hidden;
            width: 0%;
            color: var(--accent);
            transition: width 0.25s ease-out;
        }

        .loader-sub {
            font-size: 12px;
            letter-spacing: 5px;
            text-transform: uppercase;
            color: var(--text-muted);
            font-weight: 500;
        }
        /* --- MENU APLIKACJE (styl Start) --- */
        .start-menu {
            width: 440px;
            max-width: calc(100vw - 12px);
            max-height: min(620px, calc(100vh - var(--taskbar-h) - 20px));
            padding: 0;
            overflow: hidden;
            border-radius: 8px;
        }

        #appsView, #appForm { display: flex; flex-direction: column; min-height: 0; flex: 1; }
        #appsView { max-height: inherit; }

        .sm-head { display: flex; gap: 8px; padding: 12px; border-bottom: 1px solid var(--border); }
        .sm-search { position: relative; flex: 1; }
        .sm-search i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 12px; }
        .sm-search input { width: 100%; padding: 8px 10px 8px 30px; background: var(--input-bg); border: 1px solid transparent; border-radius: 6px; color: var(--text-main); font-size: 13px; outline: none; }
        .sm-search input:focus { border-color: var(--accent); outline: none; }
        .sm-add { background: var(--accent); color: #fff; border: none; border-radius: 6px; padding: 0 14px; font-size: 12px; font-weight: 600; cursor: pointer; white-space: nowrap; }
        .sm-add:hover { background: var(--accent-hover); }

        .sm-body { overflow-y: auto; padding: 6px 8px 10px; min-height: 120px; }
        .sm-section { padding: 10px 8px 6px; font-size: 10px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: var(--text-muted); display: flex; justify-content: space-between; }

        .sm-pinned { display: grid; grid-template-columns: repeat(4, 1fr); gap: 4px; padding: 0 2px 6px; }
        .sm-tile { position: relative; display: flex; flex-direction: column; align-items: center; gap: 7px; padding: 12px 4px 10px; border-radius: 6px; cursor: pointer; transition: background .12s; }
        .sm-tile:hover, .sm-row:hover, .sm-row.focus, .sm-tile.focus { background: var(--item-hover); }
        .sm-tile .ico { width: 40px; height: 40px; border-radius: 10px; display: grid; place-items: center; font-size: 18px; color: #fff; background: var(--accent); }
        .sm-tile .nm { font-size: 11px; text-align: center; line-height: 1.25; max-width: 100%; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; }

        .sm-row { display: flex; align-items: center; gap: 12px; padding: 7px 8px; border-radius: 6px; cursor: pointer; transition: background .12s; }
        .sm-row .ico { width: 32px; height: 32px; flex-shrink: 0; border-radius: 8px; display: grid; place-items: center; font-size: 14px; color: #fff; background: var(--accent); }
        .sm-row .txt { flex: 1; min-width: 0; }
        .sm-row .nm { font-size: 13px; color: var(--text-bright); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sm-row .url { font-size: 11px; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sm-letter { padding: 8px 8px 2px; font-size: 11px; font-weight: 700; color: var(--accent); }

        .sm-actions { display: flex; gap: 2px; opacity: 0; transition: opacity .12s; }
        .sm-row:hover .sm-actions, .sm-row.focus .sm-actions { opacity: 1; }
        .sm-tile .sm-actions { position: absolute; top: 2px; right: 2px; }
        .sm-tile:hover .sm-actions { opacity: 1; }
        .sm-icon-btn { background: none; border: none; color: var(--text-muted); width: 26px; height: 26px; border-radius: 5px; cursor: pointer; font-size: 12px; }
        .sm-icon-btn:hover { background: rgba(255, 255, 255, .1); color: #fff; }
        .sm-icon-btn.on { color: var(--accent-hover); }
        .sm-icon-btn.danger:hover { background: var(--red); color: #fff; }

        .sm-empty { text-align: center; color: var(--text-muted); padding: 34px 10px; line-height: 1.7; }
        .sm-empty i { display: block; font-size: 26px; margin-bottom: 8px; opacity: .6; }
        .sm-foot { display: flex; justify-content: space-between; padding: 8px 14px; border-top: 1px solid var(--border); font-size: 11px; color: var(--text-muted); }

        .sm-form-title { display: flex; align-items: center; gap: 8px; padding: 12px; border-bottom: 1px solid var(--border); font-weight: 600; }
        .sm-form { padding: 14px; overflow-y: auto; }
        .sm-form label { display: block; margin: 4px 0; font-size: 10px; text-transform: uppercase; letter-spacing: .8px; color: var(--text-muted); }
        .sm-form .app-input { margin-bottom: 12px; }
        /* --- MENU POD PRAWYM PRZYCISKIEM --- */
        .ctx-menu { position: fixed; z-index: 3500; min-width: 230px; background: #2d2d30; border: 1px solid #454545; border-radius: 8px; padding: 5px; box-shadow: 0 12px 34px rgba(0, 0, 0, .55); display: none; font-size: 12.5px; }
        .ctx-menu.show { display: block; }
        .ctx-head { padding: 6px 10px 7px; color: var(--text-muted); font-size: 11px; max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; border-bottom: 1px solid var(--border); margin-bottom: 4px; }
        .ctx-item { display: flex; align-items: center; gap: 10px; padding: 7px 10px; border-radius: 5px; cursor: pointer; color: var(--text-main); }
        .ctx-item i { width: 16px; text-align: center; color: var(--accent-hover); }
        .ctx-item:hover { background: var(--accent-dim); color: #fff; }
        .ctx-item.danger i { color: #f14c4c; }
        .ctx-item.danger:hover { background: rgba(244, 71, 71, .18); }
        .ctx-sep { height: 1px; background: var(--border); margin: 4px 2px; }
        .tree-item.ctx-target { background: var(--item-active); outline: 1px solid var(--accent); outline-offset: -1px; }

        /* --- PULPIT --- */
        .dash { width: 100%; max-width: 1180px; margin: 0 auto; }
        .dash-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 20px; flex-wrap: wrap; margin-bottom: 26px; }
        .dash-hello { color: var(--accent); font-size: 12px; font-weight: 600; letter-spacing: 1.5px; text-transform: uppercase; margin-bottom: 4px; }
        .dash h1 { font-size: 2rem; font-weight: 600; color: var(--text-bright); margin: 0; letter-spacing: -0.4px; }
        .dash-meta { color: var(--text-muted); font-size: 12px; margin-top: 4px; }
        .dash-side { display: flex; flex-direction: column; align-items: flex-end; gap: 10px; }
        .dash-status { display: flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end; }
        .dash-chip { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background: var(--card-bg); border: 1px solid var(--border); border-radius: 20px; font-size: 11px; color: var(--text-main); }
        .dash-chip .status-badge { margin-right: 0; }
        .dash-search { display: flex; align-items: center; gap: 8px; background: var(--input-bg); color: var(--text-muted); border: 1px solid var(--border); border-radius: 6px; padding: 8px 12px; font-size: 12px; cursor: pointer; font-family: inherit; }
        .dash-search:hover { border-color: var(--accent); color: var(--text-main); }
        kbd { background: #1e1e1e; border: 1px solid var(--border); border-radius: 4px; padding: 1px 6px; font-size: 10px; font-family: 'JetBrains Mono', monospace; margin-left: 6px; }

        .dash-sec { background: var(--card-bg); border: 1px solid var(--border); border-radius: 8px; padding: 16px 18px 18px; min-width: 0; }
        .dash-sec.full { margin-bottom: 16px; }
        .dash-sec h3 { margin: 0 0 14px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.4px; color: var(--text-muted); display: flex; align-items: center; gap: 8px; }
        .dash-sec h3 i { color: var(--accent); }
        .dash-badge { margin-left: auto; background: var(--accent-dim); color: var(--accent-hover); border-radius: 10px; padding: 1px 8px; font-size: 10px; letter-spacing: 0; }
        .dash-cols { display: grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap: 16px; }
        .dash-scroll { max-height: 300px; overflow-y: auto; margin-right: -6px; padding-right: 6px; }

        .dash-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 10px; }
        .dash-card { background: var(--bg-dark); border: 1px solid var(--border); border-radius: 8px; padding: 14px; cursor: pointer; transition: border-color .15s, transform .15s; display: flex; flex-direction: column; gap: 8px; }
        .dash-card:hover { border-color: var(--accent); transform: translateY(-2px); }
        .dash-card-top { display: flex; align-items: center; gap: 8px; color: var(--text-bright); font-size: 13px; }
        .dash-card-top i.fa-folder { color: #c8a96e; }
        .dash-card-desc { color: var(--text-muted); font-size: 12px; line-height: 1.5; min-height: 36px; display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .dash-card-foot { display: flex; gap: 14px; font-size: 11px; color: var(--text-muted); align-items: center; }
        .dash-card-foot a { margin-left: auto; color: var(--text-muted); }
        .dash-card-foot a:hover { color: var(--accent-hover); }

        .todo-group { margin-bottom: 12px; }
        .todo-proj a { font-size: 11px; font-weight: 600; color: var(--accent-hover); text-decoration: none; }
        .todo-line { display: flex; gap: 10px; align-items: flex-start; padding: 5px 0; cursor: pointer; font-size: 12.5px; line-height: 1.4; transition: opacity .3s; }
        .todo-line input { margin-top: 2px; accent-color: var(--accent); }
        .todo-line.done { opacity: .35; text-decoration: line-through; }

        .att-row { display: flex; flex-direction: column; gap: 2px; padding: 8px 10px; border-radius: 6px; cursor: pointer; }
        .att-row:hover { background: var(--item-hover); }
        .att-row b { font-weight: 500; color: var(--text-bright); font-size: 12.5px; }
        .att-row span { font-size: 11px; color: var(--text-muted); }

        .dash-sub { font-size: 10px; text-transform: uppercase; letter-spacing: 1.2px; color: var(--text-muted); margin: 4px 0 8px; }
        .dash-tiles { display: grid; grid-template-columns: repeat(auto-fill, minmax(90px, 1fr)); gap: 8px; margin-bottom: 14px; }
        .dash-tile { display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 12px 6px; border-radius: 8px; background: var(--bg-dark); border: 1px solid var(--border); text-decoration: none; color: var(--text-main); font-size: 11px; text-align: center; }
        .dash-tile:hover { border-color: var(--accent); }
        .dash-tile i { font-size: 18px; color: var(--accent-hover); }
        .dash-chips { display: flex; flex-wrap: wrap; gap: 6px; }
        .dash-chips a { padding: 4px 10px; background: var(--bg-dark); border: 1px solid var(--border); border-radius: 14px; font-size: 12px; color: var(--text-main); text-decoration: none; }
        .dash-chips a:hover { border-color: var(--accent); }
        .dash-chips i { color: var(--yellow); font-size: 10px; }

        .file-row { display: flex; align-items: center; gap: 10px; padding: 7px 8px; border-radius: 6px; text-decoration: none; color: var(--text-main); font-size: 12px; }
        .file-row:hover { background: var(--item-hover); }
        .file-row i { color: var(--accent-hover); }
        .file-row .fn { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; direction: rtl; text-align: left; }
        .file-row .file-web { color: var(--text-muted); font-size: 11px; padding: 2px 4px; border-radius: 3px; }
        .file-row .file-web:hover { color: var(--accent-hover); background: var(--item-active); }
        .file-row .ft { color: var(--text-muted); font-size: 11px; white-space: nowrap; }

        .dash-empty { text-align: center; color: var(--text-muted); padding: 24px 10px; line-height: 1.7; font-size: 12px; }
        .dash-empty i { display: block; font-size: 24px; margin-bottom: 8px; opacity: .6; }

        /* Paleta Ctrl+K */
        #palette { position: fixed; inset: 0; background: rgba(0, 0, 0, .55); backdrop-filter: blur(2px); z-index: 4000; display: none; justify-content: center; align-items: flex-start; padding-top: 12vh; }
        #palette.active { display: flex; }
        .pal-box { width: min(620px, 92vw); background: #2d2d30; border: 1px solid #454545; border-radius: 10px; box-shadow: 0 24px 60px rgba(0, 0, 0, .6); overflow: hidden; }
        #palInput { width: 100%; padding: 16px 18px; background: transparent; border: none; border-bottom: 1px solid var(--border); color: var(--text-bright); font-size: 15px; outline: none !important; font-family: inherit; }
        #palList { max-height: 50vh; overflow-y: auto; padding: 6px; }
        .pal-row { display: flex; align-items: center; gap: 12px; padding: 9px 12px; border-radius: 6px; cursor: pointer; }
        .pal-row.sel, .pal-row:hover { background: var(--accent-dim); }
        .pal-row i { width: 18px; text-align: center; color: var(--accent-hover); }
        .pal-row .pt { color: var(--text-bright); font-size: 13px; white-space: nowrap; }
        .pal-row .ps { flex: 1; color: var(--text-muted); font-size: 11px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .pal-row .pk { margin-left: auto; font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); }
        /* --- LOGO TEKSTOWE (Keania One) --- */
        .brand-wrap { container-type: inline-size; min-width: 0; flex: 1; }
        .brand {
            --brand-max: 26px;
            display: inline-block;
            font-size: min(var(--brand-max), 11.5cqw);
            line-height: 1;
            white-space: nowrap;
            user-select: none;
            text-align: right; /* "ver." wyrównane do prawej krawędzi napisu */
        }
        .brand-name { display: block; font-family: 'Keania One', Impact, 'Arial Black', sans-serif; letter-spacing: 0.02em; text-align: left; }
        .brand-name .b-mr { color: #f1f1f1; }
        .brand-name .b-prompt { color: #e31b1b; }
        .brand-name .b-server { color: #9b9b9b; }
        .brand-ver { display: block; margin-top: 0.14em; font-family: 'Keania One', Impact, sans-serif; font-size: 0.42em; color: #8c8c8c; letter-spacing: 0.04em; }
        .brand-lg { --brand-max: 44px; }
        .about-brand { container-type: inline-size; margin-bottom: 20px; }

        /* =====================================================
           RUCH: miękkie animacje w stylu Apple (tylko transform/opacity)
           ===================================================== */
        :root { --ease: cubic-bezier(0.22, 1, 0.36, 1); --ease-soft: cubic-bezier(0.32, 0.72, 0, 1); }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes rise { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
        @keyframes viewIn { from { opacity: 0; transform: translateY(10px) scale(0.995); } to { opacity: 1; transform: none; } }
        @keyframes popIn { from { opacity: 0; transform: translateY(8px) scale(0.94); } to { opacity: 1; transform: none; } }
        @keyframes menuUp { from { opacity: 0; transform: translateY(12px) scale(0.96); } to { opacity: 1; transform: none; } }
        @keyframes popMenu { from { opacity: 0; transform: scale(0.94); } to { opacity: 1; transform: none; } }
        @keyframes popDown { from { opacity: 0; transform: translateY(-10px) scale(0.97); } to { opacity: 1; transform: none; } }
        @keyframes nestIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: none; } }
        @keyframes growW { from { transform: scaleX(0); } to { transform: scaleX(1); } }
        @keyframes toastIn { from { opacity: 0; transform: translateX(28px) scale(0.96); } to { opacity: 1; transform: none; } }

        /* przełączanie widoków w części głównej */
        #mainContent > * { animation: viewIn 0.5s var(--ease) backwards; }
        #mainContent > .dash { animation: none; }
        .dash > * { animation: rise 0.6s var(--ease) backwards; }
        .dash > :nth-child(1) { animation-delay: 0ms; }
        .dash > :nth-child(2) { animation-delay: 70ms; }
        .dash > :nth-child(3) { animation-delay: 140ms; }
        .dash .dash-cols > .dash-sec { animation: rise 0.6s var(--ease) backwards; }
        .dash .dash-cols > :nth-child(1) { animation-delay: 190ms; }
        .dash .dash-cols > :nth-child(2) { animation-delay: 240ms; }
        .dash .dash-cols > :nth-child(3) { animation-delay: 290ms; }
        .dash .dash-cols > :nth-child(4) { animation-delay: 340ms; }
        .dash-card { animation: rise 0.55s var(--ease) backwards; }
        .dash-card:nth-child(1) { animation-delay: 120ms; } .dash-card:nth-child(2) { animation-delay: 165ms; }
        .dash-card:nth-child(3) { animation-delay: 210ms; } .dash-card:nth-child(4) { animation-delay: 255ms; }
        .dash-card:nth-child(5) { animation-delay: 300ms; } .dash-card:nth-child(6) { animation-delay: 345ms; }
        .dash.no-enter, .dash.no-enter * { animation: none !important; }

        .stats-card { animation: rise 0.55s var(--ease) backwards; }
        .stats-card:nth-child(2) { animation-delay: 50ms; } .stats-card:nth-child(3) { animation-delay: 100ms; }
        .stats-card:nth-child(4) { animation-delay: 150ms; } .stats-card:nth-child(5) { animation-delay: 200ms; }
        .lang-card { animation: rise 0.5s var(--ease) backwards; }
        .lang-card:nth-child(2) { animation-delay: 60ms; } .lang-card:nth-child(3) { animation-delay: 120ms; }
        .lang-card:nth-child(4) { animation-delay: 180ms; } .lang-card:nth-child(5) { animation-delay: 240ms; }
        .progress-fill { transform-origin: left center; animation: growW 1s var(--ease) 0.15s backwards; }
        .stats-accordion.active .stats-accordion-body { animation: rise 0.4s var(--ease) backwards; }
        .stats-accordion-header i { transition: transform 0.3s var(--ease); }
        .stats-accordion.active .stats-accordion-header i { transform: rotate(180deg); }

        .tab-content.active { animation: rise 0.4s var(--ease) backwards; }
        .note-body { animation: rise 0.35s var(--ease) backwards; }
        .todo-item { animation: rise 0.35s var(--ease) backwards; }

        /* okna, menu, powiadomienia */
        .modal-overlay.active { animation: fadeIn 0.22s ease backwards; }
        .modal-overlay.active .modal-box { animation: popIn 0.4s var(--ease) backwards; }
        .start-menu.active { animation: menuUp 0.34s var(--ease) backwards; transform-origin: bottom left; }
        .ctx-menu.show { animation: popMenu 0.18s var(--ease) backwards; transform-origin: top left; }
        #palette.active { animation: fadeIn 0.2s ease backwards; }
        #palette.active .pal-box { animation: popDown 0.34s var(--ease) backwards; }
        .toast { animation: toastIn 0.5s var(--ease) backwards; }
        #global-tooltip { animation: fadeIn 0.15s ease backwards; }

        /* drzewo projektów */
        .nested.active { animation: nestIn 0.3s var(--ease) backwards; }
        .folder-toggle i { transition: transform 0.28s var(--ease); }
        .folder-toggle.open i { transform: rotate(90deg); }
        .tree-item { transition: background 0.18s ease; }
        aside { transition: width 0.4s var(--ease), min-width 0.4s var(--ease); }
        .fav-star, .github-icon, .info-badge, .trash-icon { transition: color 0.18s ease, transform 0.25s var(--ease), opacity 0.18s ease; }
        .fav-star:active { transform: scale(0.8); }

        /* mikro-interakcje: hover i wciśnięcie */
        .sm-add, .app-save, .btn-refresh, .dash-search, .dash-chip, .filter-link, .tab-btn, .btn-action, .icon-opt, .sm-icon-btn, .start-button, .tb-chip, .nav-link, .ctx-item, .sm-tile, .sm-row, .dash-tile, .att-row, .file-row {
            transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease, transform 0.25s var(--ease), box-shadow 0.25s ease, opacity 0.2s ease;
        }
        button:active, .nav-link:active, .start-button:active, .sm-tile:active, .dash-tile:active { transform: scale(0.965); }
        .sm-row:active, .att-row:active, .file-row:active, .ctx-item:active { transform: scale(0.99); }
        .dash-card { transition: border-color 0.25s ease, transform 0.35s var(--ease), box-shadow 0.35s ease; }
        .dash-card:hover { transform: translateY(-3px); box-shadow: 0 14px 34px rgba(0, 0, 0, 0.4); }
        .dash-card:active { transform: translateY(-1px) scale(0.985); }
        .dash-tile:hover { transform: translateY(-2px); }
        .dash-search:hover { transform: translateY(-1px); }

        /* loader: napis czcionką Keania One */
        .loader-word { font-family: 'Keania One', Impact, 'Arial Black', sans-serif; font-weight: 400; letter-spacing: 0.02em; opacity: 0; transition: opacity 0.5s ease; }
        #app-loader.font-ready .loader-word { opacity: 1; }
        .loader-sub { opacity: 0; animation: fadeIn 0.8s ease 0.5s forwards; }
        #app-loader { transition: opacity 0.6s var(--ease), visibility 0.6s, transform 0.6s var(--ease); }
        #app-loader.done { transform: scale(1.04); }

        /* dolny pasek stanu */
        .taskbar { gap: 10px; }
        .tb-ctx { color: rgba(255, 255, 255, 0.92); font-size: 11.5px; max-width: 34vw; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; cursor: pointer; padding: 3px 8px; border-radius: 4px; display: none; }
        .tb-ctx.on { display: block; }
        .tb-ctx:hover { background: rgba(0, 0, 0, 0.18); }
        .tb-right { display: flex; align-items: center; gap: 6px; margin-left: auto; height: 100%; padding: 3px 0; }
        .tb-chip { display: inline-flex; align-items: center; gap: 6px; background: rgba(0, 0, 0, 0.16); border: none; color: rgba(255, 255, 255, 0.95); padding: 0 9px; height: 20px; border-radius: 10px; font-size: 11px; font-family: inherit; cursor: default; white-space: nowrap; }
        button.tb-chip { cursor: pointer; }
        button.tb-chip:hover { background: rgba(0, 0, 0, 0.3); }
        .tb-chip.warn { background: rgba(255, 255, 255, 0.92); color: #7a5200; font-weight: 600; }
        .tb-chip.bad { background: #b3261e; color: #fff; font-weight: 600; animation: tbPulse 1.6s ease-in-out infinite; }
        @keyframes tbPulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(179, 38, 30, 0.6); } 50% { box-shadow: 0 0 0 5px rgba(179, 38, 30, 0); } }
        .tb-dot { width: 7px; height: 7px; border-radius: 50%; background: #7dffb0; box-shadow: 0 0 6px rgba(125, 255, 176, 0.8); display: inline-block; }
        .tb-dot.off { background: #ff6b6b; box-shadow: 0 0 6px rgba(255, 107, 107, 0.9); }
        .tb-hint { color: rgba(255, 255, 255, 0.78); font-size: 11px; margin-left: 4px; white-space: nowrap; }
        .tb-hint kbd { background: rgba(0, 0, 0, 0.22); border: none; color: #fff; margin: 0 2px 0 6px; font-size: 10px; }
        @media (max-width: 1320px) { .tb-hint { display: none; } }
        @media (max-width: 1120px) { .tb-recent { display: none !important; } }
        @media (max-width: 900px) { .tb-php, .tb-disk { display: none !important; } .tb-ctx { max-width: 24vw; } }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: 0.01ms !important; animation-delay: 0s !important; animation-iteration-count: 1 !important; transition-duration: 0.01ms !important; scroll-behavior: auto !important; }
        }
    </style>

    <link rel="stylesheet" href="mrprompt_server/diag.css">
</head>

<body>
    <div id="app-loader" aria-label="Ładowanie aplikacji">
        <div class="loader-word">MrPrompt<span class="fill" id="loaderFill">MrPrompt</span></div>
        <div class="loader-sub">Centrum Otwartych Innowacji</div>
    </div>
    <script>
        // Loader: pasek "napełniania" napisu; kończy się po załadowaniu strony (lub po limicie czasu)
        (function() {
            const fill = document.getElementById('loaderFill');
            const loader = document.getElementById('app-loader');
            const start = Date.now();
            let pct = 0, closed = false;
            const tick = setInterval(() => {
                pct += (90 - pct) * 0.08; // zwalnia zbliżając się do 90%
                fill.style.width = pct.toFixed(1) + '%';
            }, 60);

            function finish() {
                if (closed) return;
                closed = true;
                clearInterval(tick);
                fill.style.width = '100%';
                const wait = Math.max(0, 700 - (Date.now() - start)); // min. czas pokazu
                setTimeout(() => {
                    loader.classList.add('done');
                    setTimeout(() => loader.remove(), 600);
                }, wait + 250);
            }
            const showWord = () => loader.classList.add('font-ready');
            if (document.fonts && document.fonts.load) document.fonts.load("1em 'Keania One'").then(showWord, showWord); else showWord();
            setTimeout(showWord, 1400); // zabezpieczenie: bez internetu pokaż napis czcionką zapasową
            window.addEventListener('load', finish);
            setTimeout(finish, 8000); // zabezpieczenie, gdy CDN nie odpowiada
        })();
    </script>
    <div id="global-tooltip"></div>

    <!-- MODAL ABOUT -->
    <div class="modal-overlay" id="aboutModal">
        <div class="modal-box">
            <span class="close-modal" onclick="toggleAboutModal()">&times;</span>
            <div class="about-brand"><span class="brand brand-lg" role="img" aria-label="MrPrompt Server ver. 4.0"><span class="brand-name"><span class="b-mr">Mr</span><span class="b-prompt">Prompt</span> <span class="b-server">Server</span></span><span class="brand-ver">ver. 4.0</span></span></div>
            <h2 style="color:var(--accent); margin:0 0 10px 0;">MrPrompt Server 4.0</h2>
            <p style="color:var(--text-muted); margin-bottom:20px;">Panel Zarządzania XAMPP</p>
            <div style="background:#0d1117; padding:15px; border-radius:6px; text-align:left; font-size:0.9rem; color:var(--text-main); margin-bottom:20px;">
                <p><strong>Wersja:</strong> 4.0 Stable</p>
                <p><strong>Twórca:</strong> MrPrompt</p>
                <p><strong>PHP:</strong> <?= phpversion() ?></p>
                <p><strong>Serwer:</strong> <?= $_SERVER['SERVER_SOFTWARE'] ?></p>
                <p><strong>Strona:</strong> <a href="https://mrprompt.eu/" target="_blank" rel="noopener" style="color:var(--accent);">mrprompt.eu</a></p>
                <p><strong>GitHub Pages:</strong> <a href="https://mrprompt24.github.io/" target="_blank" rel="noopener" style="color:var(--accent);">mrprompt24.github.io</a></p>
            </div>
            <button onclick="toggleAboutModal()" style="background:var(--accent); color:white; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-weight:600;">Zamknij</button>
        </div>
    </div>

    <div class="start-menu" id="startMenu">
        <!-- Widok listy -->
        <div id="appsView">
            <div class="sm-head">
                <div class="sm-search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="appSearch" autocomplete="off" placeholder="Szukaj aplikacji…">
                </div>
                <button class="sm-add" id="appAddBtn" title="Dodaj aplikację"><i class="fa-solid fa-plus"></i> Dodaj</button>
            </div>
            <div class="sm-body" id="appsBody"></div>
            <div class="sm-foot"><span id="appsCount"></span><span>Enter – otwórz pierwszy wynik</span></div>
        </div>

        <!-- Widok formularza (dodawanie / edycja) -->
        <div id="appForm" style="display:none;">
            <div class="sm-form-title">
                <button class="sm-icon-btn" id="appFormBack" title="Wróć"><i class="fa-solid fa-arrow-left"></i></button>
                <span id="appFormTitle">Nowa aplikacja</span>
            </div>
            <form id="appFormEl" class="sm-form" autocomplete="off">
                <input type="hidden" id="appId">
                <input type="hidden" id="appIconInput" value="fa-solid fa-globe">
                <label>Nazwa</label>
                <input type="text" id="appName" class="app-input" placeholder="np. Mój sklep" required>
                <label>Adres URL</label>
                <input type="text" id="appUrl" class="app-input" placeholder="http://localhost/moja-app" required>
                <label>Ikona</label>
                <div class="icon-picker" id="iconPicker">
                    <?php foreach (APP_ICONS as $ic): ?>
                        <button type="button" class="icon-opt" data-icon="<?= $ic ?>" title="<?= $ic ?>"><i class="<?= $ic ?>"></i></button>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="app-save"><i class="fa-solid fa-check"></i> Zapisz</button>
            </form>
        </div>
    </div>

    <div id="ctxMenu" class="ctx-menu" role="menu"></div>
    <div id="fsModal" class="modal-overlay">
        <div class="modal-box" style="text-align:left;">
            <h3 id="fsTitle" style="margin:0 0 6px; color:var(--text-bright);"></h3>
            <p id="fsHint" style="margin:0 0 12px; color:var(--text-muted); font-size:12px;"></p>
            <input type="text" id="fsInput" class="app-input" autocomplete="off" spellcheck="false">
            <div id="fsErr" style="color:var(--red); font-size:12px; min-height:16px; margin-bottom:8px;"></div>
            <div style="display:flex; justify-content:flex-end; gap:8px;">
                <button class="sm-add" style="background:#3c3c3c" id="fsCancel">Anuluj</button>
                <button class="sm-add" id="fsOk">OK</button>
            </div>
        </div>
    </div>

    <div id="palette">
        <div class="pal-box">
            <input type="text" id="palInput" autocomplete="off" placeholder="Szukaj projektu, aplikacji lub narzędzia…">
            <div id="palList"></div>
        </div>
    </div>

    <div class="taskbar">
        <button class="start-button" id="startBtn"><i class="fa-solid fa-table-cells-large"></i> Aplikacje</button>
        <div class="tb-ctx" id="tbCtx" title="Kliknij, aby skopiować ścieżkę"></div>
        <div class="tb-right" id="tbRight"></div>
    </div>

    <aside id="main-sidebar">
        <div class="sidebar-header">
            <div class="header-top">
                <div class="brand-wrap"><span class="brand" role="img" aria-label="MrPrompt Server ver. 4.0"><span class="brand-name"><span class="b-mr">Mr</span><span class="b-prompt">Prompt</span> <span class="b-server">Server</span></span><span class="brand-ver">ver. 4.0</span></span></div>
                <span class="toggle-full-view" onclick="toggleSidebarFullView()" title="Przełącz pełny widok">
                    <i class="fa-solid fa-expand"></i>
                </span>
            </div>
            <input type="text" id="treeSearch" autocomplete="off" class="search-box" placeholder="Szukaj projektu...">
            <div class="side-actions">
                <button class="btn-refresh" onclick="location.reload()" title="Przeładuj całą aplikację"><i class="fa-solid fa-rotate"></i> Odśwież</button>
                <button class="btn-refresh accent" onclick="diagNewProject()" title="Kreator nowego projektu"><i class="fa-solid fa-plus"></i> Nowy projekt</button>
            </div>
            <div class="filter-bar">
                <span class="filter-link active" onclick="filterTree('all', this)">Wszystkie</span>
                <span class="filter-link" onclick="filterTree('recent', this)">Ostatnie</span>
                <span class="filter-link" onclick="filterTree('favs', this)">Ulubione</span>
            </div>
        </div>
        <div id="file-tree-container">
            <?= renderTree($_SERVER['DOCUMENT_ROOT'], $favPaths, true) ?>
        </div>
    </aside>

    <main>
        <header>
            <nav class="nav-menu">
                <a href="javascript:void(0)" class="nav-link" onclick="goHome()" title="Strona startowa" aria-label="Strona startowa"><i class="fa-solid fa-house"></i></a>
                <a href="javascript:void(0)" class="nav-link" onclick="loadServerStats()">Statystyki Serwera</a>
                <a href="javascript:void(0)" class="nav-link" onclick="openDiag()" title="Logi błędów, porty i procesy, bazy danych">Diagnostyka</a>
                <a href="mrprompt_server/edytor/index.php" target="_blank" class="nav-link">Edytor</a>
                <a href="javascript:void(0)" class="nav-link" onclick="loadCzytnikMD()">Czytnik MD</a>
                <a href="javascript:void(0)" class="nav-link" onclick="loadCzytnikJSON()">Czytnik JSON</a>
                <a href="javascript:void(0)" class="nav-link" onclick="openCmd()">CMD</a>
                <a href="mrprompt_server/menager_baz_idexdb" class="nav-link" target="_blank">Bazy IndexDB</a>
                <a href="http://localhost/dashboard/phpinfo.php" class="nav-link" target="_blank">PHPinfo</a>
                <a href="http://localhost/phpmyadmin/" class="nav-link" target="_blank">phpMyAdmin</a>
                <a href="javascript:void(0)" class="nav-link" onclick="toggleAboutModal()">O Aplikacji</a>
                <a href="https://github.com/" class="nav-link" target="_blank" rel="noopener" title="GitHub"><i class="fa-brands fa-github"></i></a>
                <a href="https://github.com/MrPrompt24" class="nav-link" target="_blank" rel="noopener" title="Profil MrPrompt24 na GitHub"><i class="fa-brands fa-github"></i>&nbsp;MrPrompt24</a>
                <a href="javascript:void(0)" class="nav-link" onclick="loadHelp()" title="Pomoc (F1)" aria-label="Pomoc"><i class="fa-solid fa-circle-question"></i></a>
            </nav>
        </header>
        <div class="content-area" id="mainContent">
            <h1 style="color:#b87a00; font-size:2.8rem; font-weight:600;">MrPrompt Server 4.0</h1>
            <p style="color:var(--text-muted);">Wybierz projekt z listy, aby zarządzać jego dokumentacją.</p>
        </div>
    </main>

    <script>
        // MENU APLIKACJE (styl Start): przypięte + lista alfabetyczna + wyszukiwarka + edycja
        const startBtn = document.getElementById('startBtn');
        const startMenu = document.getElementById('startMenu');
        const appSearch = document.getElementById('appSearch');
        const appsBody = document.getElementById('appsBody');
        let APPS = <?= json_encode($apps, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
        let focusIdx = -1;

        function showAppsView() {
            document.getElementById('appForm').style.display = 'none';
            document.getElementById('appsView').style.display = 'flex';
        }

        function openMenu(open) {
            startMenu.classList.toggle('active', open);
            if (open) {
                showAppsView();
                appSearch.value = '';
                renderApps();
                setTimeout(() => appSearch.focus(), 30);
            }
        }

        startBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            openMenu(!startMenu.classList.contains('active'));
        });
        startMenu.addEventListener('click', (e) => e.stopPropagation());
        document.addEventListener('click', () => startMenu.classList.remove('active'));
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') startMenu.classList.remove('active');
        });

        function appActions(a) {
            return `<div class="sm-actions">
                <button class="sm-icon-btn ${+a.pinned ? 'on' : ''}" data-op="pin" data-id="${a.id}" title="${+a.pinned ? 'Odepnij' : 'Przypnij na górze'}"><i class="fa-solid fa-thumbtack"></i></button>
                <button class="sm-icon-btn" data-op="edit" data-id="${a.id}" title="Edytuj"><i class="fa-solid fa-pen"></i></button>
                <button class="sm-icon-btn danger" data-op="del" data-id="${a.id}" title="Usuń"><i class="fa-solid fa-trash"></i></button>
            </div>`;
        }

        function renderApps() {
            const q = appSearch.value.trim().toLowerCase();
            const list = APPS.filter(a => !q || (a.name + ' ' + a.url).toLowerCase().includes(q))
                .sort((a, b) => a.name.localeCompare(b.name, 'pl', { sensitivity: 'base' }));
            const pinned = q ? [] : list.filter(a => +a.pinned);
            let html = '';

            if (pinned.length) {
                html += `<div class="sm-section"><span>Przypięte</span></div><div class="sm-pinned">` +
                    pinned.map(a => `<div class="sm-tile" data-url="${escapeHtml(a.url)}" title="${escapeHtml(a.url)}">
                        ${appActions(a)}
                        <div class="ico"><i class="${escapeHtml(a.icon)}"></i></div>
                        <div class="nm">${escapeHtml(a.name)}</div></div>`).join('') + `</div>`;
            }

            if (list.length) {
                html += `<div class="sm-section"><span>${q ? 'Wyniki' : 'Wszystkie aplikacje'}</span></div>`;
                let letter = '';
                list.forEach(a => {
                    const l = (a.name[0] || '#').toUpperCase();
                    if (!q && l !== letter) { letter = l; html += `<div class="sm-letter">${escapeHtml(l)}</div>`; }
                    html += `<div class="sm-row" data-url="${escapeHtml(a.url)}">
                        <div class="ico"><i class="${escapeHtml(a.icon)}"></i></div>
                        <div class="txt"><div class="nm">${escapeHtml(a.name)}</div><div class="url">${escapeHtml(a.url)}</div></div>
                        ${appActions(a)}</div>`;
                });
            } else {
                html += `<div class="sm-empty"><i class="fa-solid ${q ? 'fa-magnifying-glass' : 'fa-table-cells-large'}"></i>${q ? 'Brak aplikacji pasujących do „' + escapeHtml(q) + '”' : 'Nie masz jeszcze aplikacji.<br>Kliknij „Dodaj”, aby dodać pierwszą.'}</div>`;
            }
            appsBody.innerHTML = html;
            document.getElementById('appsCount').textContent = APPS.length + ' ' + (APPS.length === 1 ? 'aplikacja' : 'aplikacji');
            focusIdx = -1;
        }

        function appCall(fields) {
            const fd = new FormData();
            Object.entries(fields).forEach(([k, v]) => fd.append(k, v));
            return fetch('mrprompt_server/apps_handler.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (d.status !== 'ok') throw new Error(d.msg || 'Błąd');
                    APPS = d.apps;
                    return d;
                });
        }

        appSearch.addEventListener('input', renderApps);

        // Nawigacja klawiaturą: strzałki + Enter
        appSearch.addEventListener('keydown', (e) => {
            const items = [...appsBody.querySelectorAll('.sm-row, .sm-tile')];
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                if (!items.length) return;
                focusIdx = (focusIdx + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
                items.forEach((el, i) => el.classList.toggle('focus', i === focusIdx));
                items[focusIdx].scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'Enter') {
                const el = items[focusIdx >= 0 ? focusIdx : 0];
                if (el) {
                    window.open(el.dataset.url, '_blank', 'noopener');
                    openMenu(false);
                }
            }
        });

        appsBody.addEventListener('click', (e) => {
            const op = e.target.closest('[data-op]');
            if (op) {
                e.stopPropagation();
                const app = APPS.find(a => String(a.id) === op.dataset.id);
                if (!app) return;
                if (op.dataset.op === 'pin') {
                    appCall({ action: 'pin', id: app.id, pinned: +app.pinned ? '' : '1' }).then(renderApps)
                        .catch(err => showToast(err.message, 'error'));
                } else if (op.dataset.op === 'edit') {
                    openAppForm(app);
                } else if (op.dataset.op === 'del') {
                    showCustomConfirm(`Usunąć aplikację <strong>${escapeHtml(app.name)}</strong> z menu?`, () => {
                        appCall({ action: 'delete', id: app.id }).then(() => { renderApps(); showToast('Aplikacja usunięta', 'success'); })
                            .catch(err => showToast(err.message, 'error'));
                    });
                }
                return;
            }
            const item = e.target.closest('.sm-row, .sm-tile');
            if (item) {
                window.open(item.dataset.url, '_blank', 'noopener');
                openMenu(false);
            }
        });

        // Formularz dodawania / edycji
        function selectIcon(icon) {
            document.getElementById('appIconInput').value = icon;
            document.querySelectorAll('.icon-opt').forEach(b => b.classList.toggle('selected', b.dataset.icon === icon));
        }

        function openAppForm(app) {
            document.getElementById('appsView').style.display = 'none';
            document.getElementById('appForm').style.display = 'flex';
            document.getElementById('appFormTitle').textContent = app ? 'Edytuj aplikację' : 'Nowa aplikacja';
            document.getElementById('appId').value = app ? app.id : '';
            document.getElementById('appName').value = app ? app.name : '';
            document.getElementById('appUrl').value = app ? app.url : '';
            selectIcon(app ? app.icon : 'fa-solid fa-globe');
            setTimeout(() => document.getElementById('appName').focus(), 30);
        }

        document.getElementById('appAddBtn').addEventListener('click', () => openAppForm(null));
        document.getElementById('appFormBack').addEventListener('click', () => { showAppsView(); appSearch.focus(); });
        document.getElementById('iconPicker').addEventListener('click', (e) => {
            const b = e.target.closest('.icon-opt');
            if (b) selectIcon(b.dataset.icon);
        });
        document.getElementById('appFormEl').addEventListener('submit', (e) => {
            e.preventDefault();
            const id = document.getElementById('appId').value;
            appCall({
                action: id ? 'update' : 'add',
                id: id,
                name: document.getElementById('appName').value,
                url: document.getElementById('appUrl').value,
                icon: document.getElementById('appIconInput').value
            }).then(() => {
                showToast(id ? 'Zapisano zmiany' : 'Aplikacja dodana', 'success');
                showAppsView();
                renderApps();
            }).catch(err => showToast(err.message, 'error'));
        });

        renderApps();

        // DRZEWO
        function toggleFolder(el) {
            const li = el.closest('li');
            const n = li.querySelector(':scope > .nested');
            if (!n) return;

            const open = !n.classList.contains('active');
            n.classList.toggle('active', open);
            el.classList.toggle('open', open);

            // Leniwe ładowanie zawartości folderu (tylko raz)
            if (open && n.dataset.lazy && !n.dataset.loaded) {
                n.dataset.loaded = '1';
                n.innerHTML = '<div style="padding:4px 10px; color:var(--text-muted); font-size:11px;"><i class="fa-solid fa-spinner fa-spin"></i></div>';
                fetch('mrprompt_server/tree_handler.php?path=' + encodeURIComponent(n.dataset.lazy))
                    .then(r => { if (!r.ok) throw new Error(); return r.text(); })
                    .then(html => { n.innerHTML = html || '<div style="padding:4px 10px; color:var(--text-muted); font-size:11px;">(pusty)</div>'; })
                    .catch(() => { delete n.dataset.loaded; n.innerHTML = '<div style="padding:4px 10px; color:var(--red); font-size:11px;">Błąd ładowania</div>'; });
            }
        }

        // Delegowana obsługa klików w drzewie (dane w atrybutach data-*, bez inline JS)
        document.getElementById('file-tree-container').addEventListener('click', (e) => {
            const toggle = e.target.closest('.folder-toggle');
            if (toggle) return toggleFolder(toggle);
            const t = e.target.closest('[data-act]');
            if (!t) return;
            e.stopPropagation();
            switch (t.dataset.act) {
                case 'open': showProjectDetails(t.dataset.path, t.dataset.name); break;
                case 'github': window.open(t.dataset.url, '_blank', 'noopener'); break;
                case 'delete': deleteFolder(t.dataset.path, t.dataset.name); break;
                case 'fav':
                    fetch('mrprompt_server/project_handler.php?ajax=1&toggle_fav=' + encodeURIComponent(t.dataset.path))
                        .then(r => r.json())
                        .then(d => {
                            t.classList.toggle('active', !!d.fav);
                            const li = t.closest('li');
                            if (li && li.parentElement === treeRoot()) li.dataset.fav = d.fav ? '1' : '0';
                            if (currentFilter === 'favs') filterTree('favs');
                        })
                        .catch(() => showToast('Nie udało się zmienić ulubionych', 'error'));
                    break;
            }
        });

        // --- FULL VIEW TOGGLE ---
        function toggleSidebarFullView() {
            const sidebar = document.getElementById('main-sidebar');
            sidebar.classList.toggle('full-view-mode');
            const icon = document.querySelector('.toggle-full-view i');
            if (sidebar.classList.contains('full-view-mode')) {
                icon.classList.remove('fa-expand');
                icon.classList.add('fa-compress');
                // Zapiszmy stan w localStorage
                localStorage.setItem('mrprompt_sidebar_full_view', '1');
            } else {
                icon.classList.remove('fa-compress');
                icon.classList.add('fa-expand');
                localStorage.setItem('mrprompt_sidebar_full_view', '0');
            }
        }

        // Przywracanie stanu
        document.addEventListener('DOMContentLoaded', () => {
            if (localStorage.getItem('mrprompt_sidebar_full_view') === '1') {
                toggleSidebarFullView(); // będzie to działanie "toggle" - jeśli jest domyślnie zwinięty, to rozwinie
            }
        });

        // FORMULARZ DOKUMENTACJI (Pobieranie i wyświetlanie)
        function escapeHtml(text) {
            if (!text) return "";
            return text
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // --- DOKUMENTACJA (Nowy System) ---
        let currentPath = '';
        let currentNotes = [];
        let currentTodos = [];

        function showProjectDetails(path, name) {
            const main = document.getElementById('mainContent');
            main.style.padding = '40px';
            main.style.alignItems = 'center';
            main.style.justifyContent = 'flex-start';
            currentPath = path;
            fetch(`mrprompt_server/get_project_info.php?path=${encodeURIComponent(path)}`)
                .then(response => response.json())
                .then(data => {
                    const safeShort = escapeHtml(data.short || '');
                    const safeCreated = escapeHtml(data.created || '');
                    const safeGithubUrl = escapeHtml(data.github_url || '');
                    currentNotes = data.notes_list || [];
                    currentTodos = data.todo || [];

                    document.getElementById('mainContent').innerHTML = `
                    <div class="project-card" style="max-width: 1000px;">
                        <h2 style="color:var(--accent); margin:0 0 20px 0;">📁 Projekt: ${name}</h2>
                        
                        <div class="tabs">
                            <button class="tab-btn active" onclick="switchTab('general')">Ogólne</button>
                            <button class="tab-btn" onclick="switchTab('docs')">Dokumentacja</button>
                            <button class="tab-btn" onclick="switchTab('todo')">Zadania (TODO)</button>
                            <button class="tab-btn" onclick="switchTab('diag')">Diagnostyka</button>
                        </div>

                        <!-- TAB: OGÓLNE -->
                        <div id="tab-general" class="tab-content active">
                            <form action="mrprompt_server/project_handler.php" method="POST" class="info-form">
                                <input type="hidden" name="action" value="save_info">
                                <input type="hidden" name="_token" value="${window.MRP_TOKEN}">
                                <input type="hidden" name="path" value="${path}">
                                <label>Krótki opis (widoczny w dymku):</label>
                                <input type="text" name="short" autocomplete="off" value="${safeShort}" placeholder="Np. Landing page dla klienta X">
                                <label>Data utworzenia / startu:</label>
                                <input type="date" name="created" value="${safeCreated}">
                                <label>Adres repozytorium GitHub:</label>
                                <input type="text" name="github_url" autocomplete="off" value="${safeGithubUrl}" placeholder="https://github.com/uzytkownik/repozytorium">
                                <p style="color:var(--text-muted); font-size:0.8rem; margin-top:20px;">Ścieżka: ${path}</p>
                                <button type="submit" style="background:var(--accent); color:white; border:none; padding:12px; width:100px; border-radius:6px; cursor:pointer; font-weight:600;">Zapisz</button>
                            </form>
                        </div>

                        <!-- TAB: DOKUMENTACJA (Nowy layout) -->
                        <div id="tab-docs" class="tab-content">
                            <!-- LIST VIEW -->
                            <div id="notes-list-view">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                                    <h3 style="margin:0; color:var(--text-muted);">Twoje notatki</h3>
                                    <button onclick="showAddNote()" style="background:var(--accent); color:white; border:none; padding:8px 16px; border-radius:6px; cursor:pointer; font-weight:600;">+ Nowa Notatka</button>
                                </div>
                                <div id="notes-list-container"></div>
                            </div>
                            
                            <!-- FORM VIEW -->
                            <div id="note-form-view" style="display:none;">
                                <h3 id="form-title" style="margin-top:0; color:var(--accent);">Nowa Notatka</h3>
                                <input type="hidden" id="note-id">
                                <input type="text" id="note-title" placeholder="Tytuł notatki" style="width:100%; padding:12px; background:#0d1117; border:1px solid var(--border); color:white; border-radius:6px; margin-bottom:15px; font-size:1.1rem;">
                                
                                <div class="md-editor-container" style="height:600px;">
                                    <textarea id="note-content" class="md-input" oninput="updateNotePreview()" placeholder="Treść notatki (Markdown)..."></textarea>
                                    <div id="note-preview" class="md-preview"></div>
                                </div>
                                
                                <div style="margin-top:20px; display:flex; gap:10px;">
                                    <button onclick="saveNote()" style="background:var(--accent); color:white; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-weight:600;">Zapisz</button>
                                    <button onclick="cancelNote()" style="background:#30363d; color:white; border:none; padding:10px 20px; border-radius:6px; cursor:pointer;">Anuluj</button>
                                </div>
                            </div>
                        </div>

                        <!-- TAB: DIAGNOSTYKA -->
                        <div id="tab-diag" class="tab-content">
                            <p style="color:var(--text-muted); margin-top:0;">Szybkie kontrole, które warto zrobić po pracy z AI albo przed publikacją projektu.</p>
                            <div class="dg-cols" style="gap:12px;">
                                <div class="dg-card"><h3><i class="fa-solid fa-heart-pulse" style="color:var(--accent2)"></i> Czy projekt działa?</h3><p style="margin:0 0 10px; font-size:12.5px; color:var(--text-muted);">Sprawdza składnię PHP, odpowiedź strony, brakujące pliki i świeże błędy w logach.</p><button class="dg-btn primary" data-diag="health"><i class="fa-solid fa-play"></i> Sprawdź teraz</button></div>
                                <div class="dg-card"><h3><i class="fa-solid fa-shield-halved" style="color:var(--accent2)"></i> Audyt przed publikacją</h3><p style="margin:0 0 10px; font-size:12.5px; color:var(--text-muted);">Szuka plików .env, folderu .git, zrzutów baz, kluczy w kodzie i innych typowych wpadek.</p><button class="dg-btn primary" data-diag="audit"><i class="fa-solid fa-magnifying-glass"></i> Skanuj projekt</button></div>
                            </div>
                            <div style="margin-top:12px;"><button class="dg-btn" data-diag="logs"><i class="fa-solid fa-bug"></i> Zobacz logi błędów tego projektu</button></div>
                        </div>

                        <!-- TAB: TODO -->
                        <div id="tab-todo" class="tab-content">
                            <div style="display:flex; gap:10px; margin-bottom:20px;">
                                <input type="text" id="newTodoInput" placeholder="Nowe zadanie..." style="flex:1; background:#0d1117; border:1px solid var(--border); color:white; padding:10px; border-radius:4px;">
                                <button onclick="addTodo()" style="background:var(--accent); color:white; border:none; padding:10px 20px; border-radius:4px; cursor:pointer;">Dodaj</button>
                            </div>
                            <div id="todoList"></div>
                            <button onclick="saveTodos()" style="margin-top:20px; background:#238636; color:white; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-weight:600;">Zapisz Listę Zadań</button>
                            <span id="saveTodoMsg" style="margin-left:10px; color:#238636; display:none;">Zapisano!</span>
                        </div>
                    </div>`;

                    renderNotesList();
                    renderTodos();
                });
        }

        // --- TABS LOGIC ---
        function switchTab(id) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            // Find button by text or index is tricky, assume order or add id to btns. 
            // Better: add onclick="switchTab('general', this)"
            // For now, let's just use querySelector with index or specific class logic.
            // Actually, I can use event.target if I pass it, but simpler:
            event.target.classList.add('active'); // This works because it's inline onclick
            document.getElementById('tab-' + id).classList.add('active');
        }

        // --- NOTES LOGIC ---
        function renderNotesList() {
            const container = document.getElementById('notes-list-container');
            if (currentNotes.length === 0) {
                container.innerHTML = '<div style="text-align:center; padding:40px; color:var(--text-muted);">Brak notatek. Dodaj pierwszą!</div>';
                return;
            }

            let html = '';
            currentNotes.forEach(note => {
                const date = note.updated_at || note.created_at || '';
                const shortDate = date ? date.substring(0, 16) : '';
                html += `
                <div class="note-item" id="note-item-${note.id}">
                    <div class="note-header" onclick="toggleNoteContent('${note.id}')">
                        <span class="note-title">${escapeHtml(note.title)}</span>
                        <div style="display:flex; align-items:center;">
                            <span class="note-meta">${shortDate}</span>
                            <div class="note-actions" onclick="event.stopPropagation()">
                                <button class="btn-action" onclick="editNote('${note.id}')"><i class="fa-solid fa-pen"></i> Edytuj</button>
                                <button class="btn-action btn-danger" onclick="deleteNote('${note.id}')"><i class="fa-solid fa-trash"></i> Usuń</button>
                                <button class="btn-action" onclick="toggleNoteContent('${note.id}')"><i class="fa-solid fa-chevron-down" id="icon-${note.id}"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="note-body" id="note-body-${note.id}">
                        <div class="md-preview" style="width:100%; height:auto; border:none; padding:0; background:transparent;">
                            ${marked.parse(note.content || '')}
                        </div>
                    </div>
                </div>`;
            });
            container.innerHTML = html;
        }

        function toggleNoteContent(id) {
            const body = document.getElementById(`note-body-${id}`);
            const icon = document.getElementById(`icon-${id}`);

            // Toggle
            if (body.style.display === 'block') {
                body.style.display = 'none';
                if (icon) icon.className = 'fa-solid fa-chevron-down';
            } else {
                body.style.display = 'block';
                if (icon) icon.className = 'fa-solid fa-chevron-up';
            }
        }

        function showAddNote() {
            document.getElementById('notes-list-view').style.display = 'none';
            document.getElementById('note-form-view').style.display = 'block';
            document.getElementById('form-title').innerText = 'Nowa Notatka';
            document.getElementById('note-id').value = '';
            document.getElementById('note-title').value = '';
            document.getElementById('note-content').value = '';
            updateNotePreview();
        }

        function editNote(id) {
            const note = currentNotes.find(n => n.id === id);
            if (!note) return;

            document.getElementById('notes-list-view').style.display = 'none';
            document.getElementById('note-form-view').style.display = 'block';
            document.getElementById('form-title').innerText = 'Edytuj Notatkę';
            document.getElementById('note-id').value = note.id;
            document.getElementById('note-title').value = note.title;
            document.getElementById('note-content').value = note.content;
            updateNotePreview();
        }

        function cancelNote() {
            document.getElementById('note-form-view').style.display = 'none';
            document.getElementById('notes-list-view').style.display = 'block';
        }

        function updateNotePreview() {
            const text = document.getElementById('note-content').value;
            document.getElementById('note-preview').innerHTML = marked.parse(text);
        }

        function saveNote() {
            const id = document.getElementById('note-id').value;
            const title = document.getElementById('note-title').value;
            const content = document.getElementById('note-content').value;

            if (!title) {
                showToast('Podaj tytuł notatki!', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'save_note');
            formData.append('path', currentPath);
            if (id) formData.append('id', id);
            formData.append('title', title);
            formData.append('content', content);

            fetch('mrprompt_server/project_handler.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'ok') {
                        showToast('Notatka zapisana pomyślnie!', 'success');
                        refreshProjectData(); // Reload data and view
                        cancelNote(); // Go back to list
                    } else {
                        showToast('Błąd zapisu!', 'error');
                    }
                });
        }

        function deleteNote(id) {
            // Custom modal replacement for verify
            // To be strictly correct with "nie systemowe", I should use a custom modal for confirmation too. 
            // I'll assume standard confirm is acceptable for now to save time, OR I can implement a quick custom confirm.
            // Let's stick to standard confirm for deletion for speed unless strictly requested, BUT user said "uwaga wszysytkie komunikaty wyłącznie z aplaikcji nie systemowe". 
            // So I MUST NOT use confirm().

            // I will use a simple custom confirm using the existing aboutModal style or similar.
            // Let's build a quick custom confirm function or just use a flag.
            // Ideally, I'd inject a simple modal into DOM.

            // Let's try to proceed with a simple custom modal injection on the fly or reuse existing structure.
            showCustomConfirm('Czy na pewno chcesz usunąć tę notatkę?', () => {
                const formData = new FormData();
                formData.append('action', 'delete_note');
                formData.append('path', currentPath);
                formData.append('id', id);

                fetch('mrprompt_server/project_handler.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'ok') {
                            showToast('Notatka usunięta!', 'success');
                            refreshProjectData();
                        } else {
                            showToast('Błąd usuwania!', 'error');
                        }
                    });
            });
        }

        function refreshProjectData() {
            fetch(`mrprompt_server/get_project_info.php?path=${encodeURIComponent(currentPath)}`)
                .then(response => response.json())
                .then(data => {
                    currentNotes = data.notes_list || [];
                    currentTodos = data.todo || [];
                    renderNotesList();
                    // renderTodos(); // Optional re-render
                });
        }

        // --- TOAST SYSTEM ---
        function showToast(msg, type = 'info') {
            let container = document.getElementById('toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toast-container';
                document.body.appendChild(container);
            }

            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `<span>${msg}</span>`;

            container.appendChild(toast);

            setTimeout(() => {
                toast.style.animation = 'slideIn 0.3s ease-in reverse forwards'; // Custom reverse animation needs CSS or just opacity
                // Webkit animation direction reverse might not work without keyframes modification.
                // Simple fade out:
                toast.style.transition = 'opacity 0.3s';
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // --- CUSTOM CONFIRM ---
        function showCustomConfirm(msg, onConfirm, label = 'Usuń') {
            // Remove existing if any
            const existing = document.getElementById('custom-confirm-overlay');
            if (existing) existing.remove();

            const overlay = document.createElement('div');
            overlay.id = 'custom-confirm-overlay';
            overlay.className = 'modal-overlay active';
            overlay.innerHTML = `
                <div class="modal-box">
                    <h3 style="color:var(--accent); margin-top:0;">Potwierdzenie</h3>
                    <p style="color:var(--text-main); margin-bottom:20px;">${msg}</p>
                    <div style="display:flex; justify-content:center; gap:10px;">
                        <button id="btn-confirm-yes" style="background:#da3633; color:white; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-weight:600;">${label}</button>
                        <button id="btn-confirm-no" style="background:#30363d; color:white; border:none; padding:10px 20px; border-radius:6px; cursor:pointer;">Anuluj</button>
                    </div>
                </div>
            `;
            document.body.appendChild(overlay);

            document.getElementById('btn-confirm-yes').onclick = () => {
                onConfirm();
                overlay.remove();
            };
            document.getElementById('btn-confirm-no').onclick = () => {
                overlay.remove();
            };
        }

        // --- USUWANIE FOLDERU ---
        function deleteFolder(path, name) {
            showCustomConfirm(`Czy na pewno chcesz trwale usunąć folder <strong>${name}</strong> wraz z jego całą zawartością z dysku komputera?<br><br><span style="color:#da3633; font-weight:bold;">UWAGA: Tej operacji nie można prosto cofnąć!</span>`, () => {
                const formData = new FormData();
                formData.append('action', 'delete_folder');
                formData.append('path', path);

                fetch('mrprompt_server/project_handler.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'ok') {
                            showToast('Folder usunięty pomyślnie!', 'success');
                            setTimeout(() => location.reload(), 1000); // Reload so the tree updates
                        } else {
                            showToast(data.message || 'Błąd usuwania folderu!', 'error');
                        }
                    })
                    .catch(err => {
                        showToast('Wystąpił błąd komunikacji z serwerem.', 'error');
                        console.error(err);
                    });
            });
        }

        function runDeploy() {
            const accountId = document.getElementById('deployAccount').value;
            const remotePath = document.getElementById('deployRemotePath').value;
            const logArea = document.getElementById('deployLog');

            if (!accountId) {
                alert('Wybierz serwer FTP!');
                return;
            }

            // Save config first
            const formData = new FormData();
            formData.append('action', 'save_deploy_config');
            formData.append('path', currentPath);
            formData.append('account_id', accountId);
            formData.append('remote_path', remotePath);
            fetch('mrprompt_server/project_handler.php', {
                method: 'POST',
                body: formData
            });

            // Start Deploy
            logArea.innerHTML = '<span class="info">Rozpoczynanie wysyłki... Proszę czekać.</span>\n';

            fetch('mrprompt_server/deploy_engine.php', {
                    method: 'POST',
                    body: JSON.stringify({
                        account_id: accountId,
                        local_path: currentPath,
                        remote_path: remotePath
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.log) {
                        data.log.forEach(l => logArea.innerHTML += `${l}\n`);
                    }
                    if (data.errors && data.errors.length > 0) {
                        data.errors.forEach(e => logArea.innerHTML += `<span class="error">[BŁĄD] ${e}</span>\n`);
                        logArea.innerHTML += `<span class="error">Zakończono z błędami.</span>\n`;
                    } else if (data.status === 'ok') {
                        logArea.innerHTML += `<span class="success">Wysyłka zakończona sukcesem!</span>\n`;
                    }
                    logArea.scrollTop = logArea.scrollHeight;
                })
                .catch(err => {
                    logArea.innerHTML += `<span class="error">[SYSTEM ERROR] ${err}</span>\n`;
                });
        }

        // --- SYSTEM TOOLS LOGIC ---
        function openCmd() {
            fetch('mrprompt_server/system_handler.php?action=open_cmd')
                .then(res => res.json())
                .then(data => {
                    if (data.status !== 'ok') alert('Błąd otwierania CMD: ' + data.msg);
                });
        }

        // --- TODO LOGIC ---
        function renderTodos() {
            const list = document.getElementById('todoList');
            list.innerHTML = '';
            currentTodos.forEach((todo, index) => {
                const div = document.createElement('div');
                div.className = `todo-item ${todo.done ? 'done' : ''}`;
                div.innerHTML = `
                    <input type="checkbox" class="todo-checkbox" ${todo.done ? 'checked' : ''} onchange="toggleTodo(${index})">
                    <span style="flex:1;">${escapeHtml(todo.text)}</span>
                    <button onclick="removeTodo(${index})" style="background:none; border:none; color:#da3633; cursor:pointer;">🗑</button>
                `;
                list.appendChild(div);
            });
        }

        function addTodo() {
            const input = document.getElementById('newTodoInput');
            if (!input.value) return;
            currentTodos.push({
                text: input.value,
                done: false
            });
            input.value = '';
            renderTodos();
        }

        function toggleTodo(index) {
            currentTodos[index].done = !currentTodos[index].done;
            renderTodos();
        }

        function removeTodo(index) {
            currentTodos.splice(index, 1);
            renderTodos();
        }

        function saveTodos() {
            const formData = new FormData();
            formData.append('action', 'save_todo');
            formData.append('path', currentPath);
            formData.append('todos', JSON.stringify(currentTodos));

            fetch('mrprompt_server/project_handler.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    const msg = document.getElementById('saveTodoMsg');
                    msg.style.display = 'inline';
                    setTimeout(() => msg.style.display = 'none', 2000);
                });
        }

        // --- MENU POD PRAWYM PRZYCISKIEM W DRZEWIE PROJEKTÓW ---
        const DOCROOT = <?= json_encode(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'])) ?>;
        const ctxMenu = document.getElementById('ctxMenu');
        let ctxTarget = null; // {path, type, name} lub {root:true}

        const normP = (p) => String(p || '').replace(/\\/g, '/').replace(/\/+$/, '');
        const parentOf = (p) => normP(p).replace(/\/[^\/]*$/, '');
        const webUrl = (p) => {
            const rel = normP(p).slice(normP(DOCROOT).length).replace(/^\//, '');
            return location.origin + '/' + rel.split('/').map(encodeURIComponent).join('/') + (rel ? '/' : '');
        };

        function fsCall(fields) {
            const fd = new FormData();
            Object.entries(fields).forEach(([k, v]) => fd.append(k, v));
            return fetch('mrprompt_server/fs_handler.php', { method: 'POST', body: fd }).then(r => r.json())
                .catch(() => ({ status: 'error', msg: 'Błąd połączenia z serwerem.' }));
        }

        // Odśwież listę w folderze (lub cały poziom główny)
        async function refreshFolderInTree(dirPath) {
            const d = normP(dirPath).toLowerCase();
            if (d === normP(DOCROOT).toLowerCase()) {
                const html = await fetch('mrprompt_server/tree_handler.php?top=1&path=' + encodeURIComponent(DOCROOT)).then(r => r.text());
                const box = document.getElementById('file-tree-container');
                box.innerHTML = html;
                treeRoot()?.querySelectorAll(':scope > li').forEach((li, i) => li.dataset.idx = i);
                filterTree(currentFilter);
                return;
            }
            const nested = [...document.querySelectorAll('.nested[data-lazy]')].find(n => normP(n.dataset.lazy).toLowerCase() === d);
            if (!nested) return;
            const html = await fetch('mrprompt_server/tree_handler.php?path=' + encodeURIComponent(nested.dataset.lazy)).then(r => r.text());
            nested.innerHTML = html || '<div style="padding:4px 10px; color:var(--text-muted); font-size:11px;">(pusty)</div>';
            nested.dataset.loaded = '1';
            nested.classList.add('active');
            const tog = nested.parentElement.querySelector(':scope > .tree-item .folder-toggle');
            if (tog) tog.classList.add('open');
        }

        function hideCtx() {
            ctxMenu.classList.remove('show');
            document.querySelectorAll('.ctx-target').forEach(e => e.classList.remove('ctx-target'));
        }

        function showCtx(x, y, t, el) {
            ctxTarget = t;
            const isDir = t.root || t.type === 'dir';
            const item = (act, icon, label, cls = '') => `<div class="ctx-item ${cls}" data-ctx="${act}"><i class="fa-solid ${icon}"></i>${label}</div>`;
            let h = `<div class="ctx-head">${escapeHtml(t.root ? 'htdocs' : t.name)}</div>`;
            if (t.root) {
                h += item('newfile', 'fa-file-circle-plus', 'Nowy plik…') + item('newfolder', 'fa-folder-plus', 'Nowy folder…') + '<div class="ctx-sep"></div>' + item('reveal', 'fa-folder-open', 'Otwórz w Eksploratorze Windows');
            } else {
                h += item('editor', 'fa-code', 'Otwórz w Edytorze');
                if (isDir) h += item('web', 'fa-globe', 'Otwórz w przeglądarce') + item('health', 'fa-heart-pulse', 'Czy projekt działa?') + item('audit', 'fa-shield-halved', 'Audyt przed publikacją');
                if (isDir) h += '<div class="ctx-sep"></div>' + item('newfile', 'fa-file-circle-plus', 'Nowy plik…') + item('newfolder', 'fa-folder-plus', 'Nowy folder…');
                h += '<div class="ctx-sep"></div>' + item('rename', 'fa-pen-to-square', 'Zmień nazwę…') + item('copypath', 'fa-copy', 'Kopiuj ścieżkę') + item('reveal', 'fa-folder-open', 'Otwórz w Eksploratorze Windows');
                h += '<div class="ctx-sep"></div>' + item('delete', 'fa-trash', 'Usuń…', 'danger');
            }
            ctxMenu.innerHTML = h;
            ctxMenu.classList.add('show');
            const w = ctxMenu.offsetWidth, hh = ctxMenu.offsetHeight;
            ctxMenu.style.left = Math.min(x, innerWidth - w - 8) + 'px';
            ctxMenu.style.top = Math.min(y, innerHeight - hh - 8) + 'px';
            document.querySelectorAll('.ctx-target').forEach(e => e.classList.remove('ctx-target'));
            if (el) el.classList.add('ctx-target');
        }

        document.getElementById('file-tree-container').addEventListener('contextmenu', (e) => {
            e.preventDefault();
            const it = e.target.closest('.tree-item[data-path]');
            if (it) showCtx(e.clientX, e.clientY, { path: it.dataset.path, type: it.dataset.type, name: it.dataset.name }, it);
            else showCtx(e.clientX, e.clientY, { root: true, path: DOCROOT }, null);
        });
        document.addEventListener('click', hideCtx);
        document.addEventListener('scroll', hideCtx, true);
        window.addEventListener('blur', hideCtx);
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') hideCtx(); });
        document.addEventListener('contextmenu', (e) => { if (!e.target.closest('#file-tree-container')) hideCtx(); });

        // Okienko z jednym polem tekstowym (nazwa)
        function askName({ title, hint, value = '', ok = 'OK' }) {
            return new Promise((resolve) => {
                const m = document.getElementById('fsModal'), inp = document.getElementById('fsInput'), err = document.getElementById('fsErr');
                document.getElementById('fsTitle').textContent = title;
                document.getElementById('fsHint').textContent = hint || '';
                document.getElementById('fsOk').textContent = ok;
                inp.value = value; err.textContent = '';
                m.classList.add('active');
                setTimeout(() => { inp.focus(); const dot = value.lastIndexOf('.'); inp.setSelectionRange(0, dot > 0 ? dot : value.length); }, 30);
                const close = (v) => { m.classList.remove('active'); cleanup(); resolve(v); };
                const onOk = () => { const v = inp.value.trim(); if (!v) { err.textContent = 'Podaj nazwę.'; return; } close(v); };
                const onKey = (e) => { if (e.key === 'Enter') onOk(); else if (e.key === 'Escape') close(null); };
                const cleanup = () => { inp.removeEventListener('keydown', onKey); document.getElementById('fsOk').onclick = null; document.getElementById('fsCancel').onclick = null; };
                inp.addEventListener('keydown', onKey);
                document.getElementById('fsOk').onclick = onOk;
                document.getElementById('fsCancel').onclick = () => close(null);
                window.__fsShowErr = (t) => { err.textContent = t; };
            });
        }

        async function createWithPrompt(kind, dir) {
            const isFile = kind === 'file';
            let name = await askName({ title: isFile ? 'Nowy plik' : 'Nowy folder', hint: 'W: ' + dir, ok: 'Utwórz', value: isFile ? 'nowy-plik.txt' : 'nowy-folder' });
            while (name) {
                const r = await fsCall({ action: isFile ? 'create_file' : 'create_folder', path: dir, name });
                if (r.status === 'ok') {
                    showToast((isFile ? 'Utworzono plik: ' : 'Utworzono folder: ') + name, 'success');
                    await refreshFolderInTree(dir);
                    return r;
                }
                showToast(r.msg || 'Nie udało się utworzyć', 'error');
                name = await askName({ title: isFile ? 'Nowy plik' : 'Nowy folder', hint: r.msg, ok: 'Utwórz', value: name });
            }
        }

        ctxMenu.addEventListener('click', async (e) => {
            const it = e.target.closest('[data-ctx]');
            if (!it || !ctxTarget) return;
            e.stopPropagation();
            const t = ctxTarget;
            hideCtx();
            const isDir = t.root || t.type === 'dir';
            switch (it.dataset.ctx) {
                case 'editor':
                    window.open(isDir ? 'mrprompt_server/edytor/index.php?project=' + encodeURIComponent(t.path)
                        : 'mrprompt_server/edytor/index.php?open=' + encodeURIComponent(t.path) + '&project=' + encodeURIComponent(parentOf(t.path)), '_blank');
                    break;
                case 'web': window.open(webUrl(t.path), '_blank', 'noopener'); break;
                case 'health': diagHealth(t.path, t.name); break;
                case 'audit': diagAudit(t.path, t.name); break;
                case 'newfile': createWithPrompt('file', t.path); break;
                case 'newfolder': createWithPrompt('folder', t.path); break;
                case 'copypath':
                    try { await navigator.clipboard.writeText(t.path.replace(/\//g, '\\')); showToast('Skopiowano ścieżkę', 'success'); }
                    catch (err) { showToast('Schowek zablokowany przez przeglądarkę', 'error'); }
                    break;
                case 'reveal': {
                    const r = await fsCall({ action: 'reveal', path: t.path });
                    if (r.status !== 'ok') showToast(r.msg || 'Nie udało się otworzyć Eksploratora', 'error');
                    break;
                }
                case 'rename': {
                    let name = await askName({ title: 'Zmień nazwę', hint: t.path, ok: 'Zmień', value: t.name });
                    while (name && name !== t.name) {
                        const r = await fsCall({ action: 'rename', path: t.path, name });
                        if (r.status === 'ok') { showToast('Zmieniono nazwę na „' + name + '”', 'success'); await refreshFolderInTree(parentOf(t.path)); return; }
                        showToast(r.msg || 'Nie udało się zmienić nazwy', 'error');
                        name = await askName({ title: 'Zmień nazwę', hint: r.msg, ok: 'Zmień', value: name });
                    }
                    break;
                }
                case 'delete':
                    if (isDir) deleteFolder(t.path, t.name);
                    else showCustomConfirm('Czy na pewno chcesz trwale usunąć plik <strong>' + escapeHtml(t.name) + '</strong>?<br><br><span style="color:#da3633; font-weight:bold;">Tej operacji nie można cofnąć.</span>', async () => {
                        const r = await fsCall({ action: 'delete', path: t.path });
                        if (r.status === 'ok') { showToast('Plik usunięty', 'success'); refreshFolderInTree(parentOf(t.path)); }
                        else showToast(r.msg || 'Nie udało się usunąć', 'error');
                    });
                    break;
            }
        });

        // SZUKAJKA I FILTRY (debounce, współpracuje z filtrem Ostatnie/Ulubione)
        let searchTimer;
        document.getElementById('treeSearch').addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => filterTree(currentFilter), 120);
        });

        let currentFilter = 'all';
        const RECENT_WINDOW = 14 * 86400; // 14 dni
        const treeRoot = () => document.querySelector('#file-tree-container > ul');

        function filterTree(type, el) {
            if (el) {
                document.querySelectorAll('.filter-link').forEach(l => l.classList.remove('active'));
                el.classList.add('active');
            }
            currentFilter = type;
            const ul = treeRoot();
            if (!ul) return;
            const items = Array.from(ul.children);
            const now = Math.floor(Date.now() / 1000);

            // Sortowanie: "Ostatnie" od najnowszych, pozostałe alfabetycznie (kolejność z serwera)
            if (type === 'recent') {
                items.sort((a, b) => (+b.dataset.mtime || 0) - (+a.dataset.mtime || 0));
            } else {
                items.sort((a, b) => (+a.dataset.idx || 0) - (+b.dataset.idx || 0));
            }
            items.forEach(li => ul.appendChild(li));

            const q = document.getElementById('treeSearch').value.trim().toLowerCase();
            items.forEach(li => {
                let show = true;
                if (type === 'favs') show = li.dataset.fav === '1';
                else if (type === 'recent') show = now - (+li.dataset.mtime || 0) < RECENT_WINDOW;
                if (show && q) {
                    const label = li.querySelector('.tree-item strong, .tree-item a');
                    show = !!label && label.textContent.toLowerCase().includes(q);
                }
                li.style.display = show ? '' : 'none';
            });
            const cnt = items.filter(li => li.style.display !== 'none').length;
            let empty = document.getElementById('tree-empty');
            if (!cnt) {
                if (!empty) {
                    empty = document.createElement('div');
                    empty.id = 'tree-empty';
                    empty.style.cssText = 'padding:20px 14px; color:var(--text-muted); font-size:12px; text-align:center;';
                    ul.parentElement.appendChild(empty);
                }
                empty.textContent = type === 'recent' ? 'Brak zmian w ostatnich 14 dniach.' : (type === 'favs' ? 'Brak ulubionych.' : 'Brak wyników.');
            } else if (empty) {
                empty.remove();
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            treeRoot()?.querySelectorAll(':scope > li').forEach((li, i) => li.dataset.idx = i);
        });

        // --- GLOBAL TOOLTIP LOGIC ---
        const tooltip = document.getElementById('global-tooltip');

        document.addEventListener('mouseover', function(e) {
            if (e.target.classList.contains('info-badge')) {
                const text = e.target.getAttribute('data-tooltip');
                if (text) {
                    tooltip.innerText = text;
                    tooltip.style.display = 'block';
                    updateTooltipPos(e);
                }
            }
        });

        document.addEventListener('mouseout', function(e) {
            if (e.target.classList.contains('info-badge')) {
                tooltip.style.display = 'none';
            }
        });

        document.addEventListener('mousemove', function(e) {
            if (tooltip.style.display === 'block') {
                updateTooltipPos(e);
            }
        });

        function updateTooltipPos(e) {
            const offset = 10;
            let top = e.clientY + offset;
            let left = e.clientX + offset;

            // Prevent going off screen
            if (left + tooltip.offsetWidth > window.innerWidth) {
                left = e.clientX - tooltip.offsetWidth - offset;
            }
            if (top + tooltip.offsetHeight > window.innerHeight) {
                top = e.clientY - tooltip.offsetHeight - offset;
            }

            tooltip.style.top = top + 'px';
            tooltip.style.left = left + 'px';
        }

        // --- MODAL LOGIC ---
        function toggleAboutModal() {
            const m = document.getElementById('aboutModal');
            m.classList.toggle('active');
        }
        document.getElementById('aboutModal').addEventListener('click', function(e) {
            if (e.target === this) this.classList.remove('active');
        });

        // --- SERVER STATS LOGIC ---
        function loadServerStats() {
            const main = document.getElementById('mainContent');
            main.style.padding = '40px';
            main.style.alignItems = 'center';
            main.style.justifyContent = 'flex-start';
            main.innerHTML = '<div style="padding:20px; text-align:center; color:var(--text-muted);"><i class="fa-solid fa-spinner fa-spin"></i> Ładowanie statystyk serwera...</div>';

            fetch('mrprompt_server/get_stats.php')
                .then(res => { if (!res.ok) throw new Error('HTTP ' + res.status); return res.json(); })
                .then(data => {
                    const e = escapeHtml;
                    const php = data.php || {}, srv = data.server || {}, sys = data.system || {};
                    const disk = data.disk || {}, db = data.mysql || {}, lite = data.sqlite || {};
                    const tools = data.tools || {}, pr = data.projects || {}, types = pr.types || {};
                    const ok = (v) => `<span class="status-badge ${v ? 'status-ok' : 'status-err'}"></span>`;
                    const kv = (k, v) => `<div class="kv"><span>${k}</span><b>${v ?? '-'}</b></div>`;
                    const dbOk = db.status === 'OK';
                    const pct = (n) => Math.min(100, Math.max(0, +n || 0));

                    const typeRow = (label, icon, color, t) => `
                        <div class="lang-card">
                            <i class="${icon}" style="color:${color}"></i>
                            <div><div class="lang-n">${(t && t.files) || 0}</div><div class="lang-l">${label}</div></div>
                            <div class="lang-s">${(t && t.size) || '0 B'}</div>
                        </div>`;

                    main.innerHTML = `
                    <div style="width:100%; max-width:1000px;">
                        <h2 style="color:var(--accent); margin:0 0 20px;"><i class="fa-solid fa-chart-simple"></i> Statystyki Serwera</h2>

                        <div class="stats-grid">
                            <div class="stats-card">
                                <h3><i class="fa-brands fa-php"></i> PHP</h3>
                                <div class="stats-big">${e(php.version || '-')}</div>
                                <div class="stats-sub">${e(php.sapi || '')} · ${e(php.arch || '')} ${e(php.ts || '')} · Zend ${e(php.zend || '')}</div>
                            </div>
                            <div class="stats-card">
                                <h3><i class="fa-solid fa-database"></i> ${e(db.flavor || 'MariaDB')}</h3>
                                <div class="stats-big">${ok(dbOk)}${dbOk ? e(db.version) : 'Wyłączony'}</div>
                                <div class="stats-sub">${dbOk ? `Bazy: ${db.count} · ${e(db.size)} · uptime ${e(db.uptime)}` : e(db.error || 'Uruchom MySQL w XAMPP')}</div>
                            </div>
                            <div class="stats-card">
                                <h3><i class="fa-solid fa-feather"></i> SQLite</h3>
                                <div class="stats-big">${e(lite.version || '-')}</div>
                                <div class="stats-sub">PDO: ${lite.pdo ? 'aktywne' : 'brak'} · apps.db ${e(lite.app_db || '-')}</div>
                            </div>
                            <div class="stats-card">
                                <h3><i class="fa-solid fa-hard-drive"></i> Dysk</h3>
                                <div class="stats-big">${disk.used_percent ?? 0}%</div>
                                <div class="stats-sub">${e(disk.free || '-')} wolne / ${e(disk.total || '-')}</div>
                                <div class="progress-bar"><div class="progress-fill" style="width:${pct(disk.used_percent)}%"></div></div>
                            </div>
                            <div class="stats-card">
                                <h3><i class="fa-solid fa-memory"></i> RAM</h3>
                                <div class="stats-big">${sys.ram_percent ?? 0}%</div>
                                <div class="stats-sub">${e(sys.ram_used || '-')} / ${e(sys.ram_total || '-')} · CPU ${sys.cpu_load}%</div>
                                <div class="progress-bar"><div class="progress-fill" style="width:${pct(sys.ram_percent)}%"></div></div>
                            </div>
                        </div>

                        <h3 class="stats-section"><i class="fa-solid fa-layer-group"></i> Twoje aplikacje: pliki wg technologii <small>(${pr.count || 0} folderów w htdocs${pr.truncated ? ', wynik częściowy' : ''})</small></h3>
                        <div class="lang-grid">
                            ${typeRow('PHP', 'fa-brands fa-php', '#8892bf', types.php)}
                            ${typeRow('HTML', 'fa-brands fa-html5', '#e44d26', types.html)}
                            ${typeRow('JavaScript', 'fa-brands fa-js', '#f7df1e', types.js)}
                            ${typeRow('CSS', 'fa-brands fa-css3-alt', '#2965f1', types.css)}
                            ${typeRow('SQLite / .db', 'fa-solid fa-feather', '#4ec994', types.sqlite)}
                        </div>

                        <div class="stats-accordion active">
                            <div class="stats-accordion-header" onclick="this.parentElement.classList.toggle('active')">
                                <strong><i class="fa-brands fa-php"></i> PHP — konfiguracja i rozszerzenia</strong><i class="fa-solid fa-chevron-down"></i>
                            </div>
                            <div class="stats-accordion-body kv-body">
                                ${kv('memory_limit', e(php.memory_limit))}
                                ${kv('upload / post max', e(php.upload_max) + ' / ' + e(php.post_max))}
                                ${kv('max_execution_time', e(php.max_execution_time))}
                                ${kv('Strefa czasowa', e(php.timezone))}
                                ${kv('OPcache', e(php.opcache) + ' (hit ' + e(php.opcache_hit_rate) + ')')}
                                ${kv('Xdebug', php.xdebug ? 'v' + e(php.xdebug) : 'wyłączony')}
                                ${kv('display_errors', php.display_errors == 1 || php.display_errors === 'On' ? 'On' : 'Off')}
                                ${kv('Rozszerzenia', (php.ext_count || 0) + ' załadowanych')}
                                <div style="grid-column:1/-1;" class="ext-list">
                                    ${(php.extensions || []).map(x => `<span class="tag">${e(x)}</span>`).join(' ')}
                                    ${(php.missing_extensions || []).map(x => `<span class="tag" style="color:var(--red); border-color:var(--red);" title="Brak">${e(x)}</span>`).join(' ')}
                                </div>
                                <div style="grid-column:1/-1; color:var(--text-muted); font-size:11px;">php.ini: ${e(php.ini || '-')}</div>
                            </div>
                        </div>

                        <div class="stats-accordion active">
                            <div class="stats-accordion-header" onclick="this.parentElement.classList.toggle('active')">
                                <strong><i class="fa-solid fa-database"></i> ${e(db.flavor || 'MariaDB')} — szczegóły</strong><i class="fa-solid fa-chevron-down"></i>
                            </div>
                            <div class="stats-accordion-body kv-body">
                                ${kv('Wersja', e(db.version))}
                                ${kv('Połączenia', e(db.connections) + ' / ' + e(db.max_connections))}
                                ${kv('Kodowanie serwera', e(db.charset))}
                                ${kv('Rozmiar danych', e(db.size))}
                                <div style="grid-column:1/-1;" class="ext-list">
                                    ${(db.databases || []).map(d => `<span class="tag">${e(d.name)} · ${e(d.size)}</span>`).join(' ') || '<span style="color:var(--text-muted)">Brak własnych baz</span>'}
                                </div>
                            </div>
                        </div>

                        <div class="stats-accordion">
                            <div class="stats-accordion-header" onclick="this.parentElement.classList.toggle('active')">
                                <strong><i class="fa-solid fa-screwdriver-wrench"></i> Narzędzia deweloperskie i serwer WWW</strong><i class="fa-solid fa-chevron-down"></i>
                            </div>
                            <div class="stats-accordion-body kv-body">
                                ${kv('Node.js', e(tools.node))}
                                ${kv('npm', e(tools.npm))}
                                ${kv('Composer', e(tools.composer))}
                                ${kv('Git', e(tools.git))}
                                ${kv('Serwer', e(srv.software))}
                                ${kv('Host:port', e((srv.host || '-')))}
                                ${kv('System', e(sys.os))}
                                ${kv('Document root', e(srv.docroot))}
                                <div style="grid-column:1/-1;">
                                    Porty:
                                    ${ok(sys.ports && sys.ports['80'])}80
                                    ${ok(sys.ports && sys.ports['443'])}443
                                    ${ok(sys.ports && sys.ports['3306'])}3306
                                </div>
                            </div>
                        </div>

                        <h3 class="stats-section"><i class="fa-solid fa-clock-rotate-left"></i> Ostatnio zmodyfikowane pliki</h3>
                        <div class="recent-box">
                            ${(pr.recent || []).map(f => `
                                <div class="recent-row">
                                    <span class="recent-file"><i class="fa-solid fa-file-code"></i> ${e(f.file)}</span>
                                    <span class="recent-time">${e(f.time)}</span>
                                </div>`).join('') || '<div class="recent-row" style="color:var(--text-muted)">Brak danych</div>'}
                        </div>
                    </div>`;
                })
                .catch(err => {
                    main.innerHTML = `<div style="color:var(--red); padding:20px;"><i class="fa-solid fa-triangle-exclamation"></i> Błąd ładowania statystyk: ${escapeHtml(String(err))}</div>`;
                });
        }
        // --- STRONA STARTOWA: PULPIT ---
        let DASH = null;

        function timeAgo(ts) {
            const d = Math.max(0, (DASH ? DASH.now : Date.now() / 1000) - ts);
            if (d < 90) return 'przed chwilą';
            if (d < 3600) return Math.round(d / 60) + ' min temu';
            if (d < 86400) return Math.round(d / 3600) + ' godz. temu';
            const days = Math.round(d / 86400);
            if (days === 1) return 'wczoraj';
            if (days < 60) return days + ' dni temu';
            return Math.round(days / 30) + ' mies. temu';
        }

        function dashSection(icon, title, badge, body, extra = '') {
            return `<section class="dash-sec ${extra}">
                <h3><i class="${icon}"></i> ${title}${badge ? `<span class="dash-badge">${badge}</span>` : ''}</h3>${body}</section>`;
        }

        function projLink(p, inner) {
            return `<a href="javascript:void(0)" data-act-dash="open" data-path="${escapeHtml(p.path)}" data-name="${escapeHtml(p.name)}">${inner}</a>`;
        }

        function renderDashboard() {
            const main = document.getElementById('mainContent');
            const d = DASH;
            const hour = new Date().getHours();
            const hello = hour < 5 ? 'Dobrej nocy' : hour < 12 ? 'Dzień dobry' : hour < 18 ? 'Miłego popołudnia' : 'Dobry wieczór';
            const s = d.server;
            const chip = (ok, label) => `<span class="dash-chip"><span class="status-badge ${ok ? 'status-ok' : 'status-err'}"></span>${label}</span>`;

            // 1. Wróć do pracy
            const recent = d.recent.map(p => `
                <div class="dash-card" data-act-dash="open" data-path="${escapeHtml(p.path)}" data-name="${escapeHtml(p.name)}">
                    <div class="dash-card-top"><i class="fa-solid fa-folder"></i><b>${escapeHtml(p.name)}</b>${p.fav ? '<i class="fa-solid fa-star" style="color:var(--yellow); margin-left:auto;"></i>' : ''}</div>
                    <div class="dash-card-desc">${p.short ? escapeHtml(p.short) : '<em>Brak opisu</em>'}</div>
                    <div class="dash-card-foot">
                        <span><i class="fa-regular fa-clock"></i> ${timeAgo(p.mtime)}</span>
                        ${p.open_todos ? `<span><i class="fa-solid fa-list-check"></i> ${p.open_todos}</span>` : ''}
                        <a href="mrprompt_server/edytor/index.php?project=${encodeURIComponent(p.path)}&panel=changes" target="_blank" rel="noopener" title="Przegląd zmian w Edytorze" onclick="event.stopPropagation()" style="margin-left:auto"><i class="fa-solid fa-code-compare"></i></a>
                        ${p.github ? `<a href="${escapeHtml(p.github)}" target="_blank" rel="noopener" title="GitHub" style="margin-left:0" onclick="event.stopPropagation()"><i class="fa-brands fa-github"></i></a>` : ''}
                    </div>
                </div>`).join('');

            // 2. Zadania
            const groups = {};
            d.todos.forEach(t => (groups[t.path] = groups[t.path] || { project: t.project, path: t.path, items: [] }).items.push(t));
            const todoHtml = Object.values(groups).map(g => `
                <div class="todo-group"><div class="todo-proj">${projLink({ path: g.path, name: g.project }, escapeHtml(g.project))}</div>
                ${g.items.map(t => `<label class="todo-line"><input type="checkbox" data-todo-path="${escapeHtml(t.path)}" data-todo-idx="${t.idx}"><span>${escapeHtml(t.text)}</span></label>`).join('')}</div>`).join('');
            const todoBody = todoHtml || `<div class="dash-empty"><i class="fa-regular fa-circle-check"></i>Brak otwartych zadań.<br><small>Dodasz je w projekcie, w zakładce „Zadania (TODO)”.</small></div>`;

            // 3. Ulubione i przypięte
            const pinnedApps = APPS.filter(a => +a.pinned);
            const favBody = (pinnedApps.length || d.favorites.length) ? `
                ${pinnedApps.length ? `<div class="dash-sub">Aplikacje</div><div class="dash-tiles">${pinnedApps.map(a => `
                    <a class="dash-tile" href="${escapeHtml(a.url)}" target="_blank" rel="noopener"><i class="${escapeHtml(a.icon)}"></i><span>${escapeHtml(a.name)}</span></a>`).join('')}</div>` : ''}
                ${d.favorites.length ? `<div class="dash-sub">Projekty</div><div class="dash-chips">${d.favorites.map(p => projLink(p, `<i class="fa-solid fa-star"></i> ${escapeHtml(p.name)}`)).join('')}</div>` : ''}`
                : `<div class="dash-empty"><i class="fa-regular fa-star"></i>Nic tu jeszcze nie ma.<br><small>Oznacz projekt gwiazdką w drzewie lub przypnij aplikację w menu „Aplikacje”.</small></div>`;

            // 5. Do uporządkowania
            const attBody = d.attention.length ? d.attention.map(a => `
                <div class="att-row" data-act-dash="open" data-path="${escapeHtml(a.path)}" data-name="${escapeHtml(a.name)}">
                    <b>${escapeHtml(a.name)}</b><span>${a.why.map(escapeHtml).join(' · ')}</span></div>`).join('')
                : `<div class="dash-empty"><i class="fa-regular fa-face-smile"></i>Wszystko uporządkowane.</div>`;

            // 6. Pliki
            const filesBody = d.files.length ? d.files.map(f => {
                const url = location.origin + '/' + f.rel.split('/').map(encodeURIComponent).join('/');
                const proj = f.abs.slice(0, f.abs.length - f.rel.length + f.rel.split('/')[0].length);
                const ed = 'mrprompt_server/edytor/index.php?open=' + encodeURIComponent(f.abs) + '&project=' + encodeURIComponent(proj);
                return `<a class="file-row" href="${escapeHtml(ed)}" target="_blank" rel="noopener" title="Otwórz w Edytorze"><i class="fa-solid fa-file-code"></i><span class="fn">${escapeHtml(f.rel)}</span><span class="file-web" data-web="${escapeHtml(url)}" title="Otwórz w przeglądarce"><i class="fa-solid fa-arrow-up-right-from-square"></i></span><span class="ft">${timeAgo(f.time)}</span></a>`;
            }).join('') : '<div class="dash-empty">Brak danych.</div>';

            const again = !!main.querySelector('.dash');
            main.style.padding = '28px 32px';
            main.style.alignItems = 'stretch';
            main.style.justifyContent = 'flex-start';
            main.innerHTML = `
            <div class="dash${again ? ' no-enter' : ''}">
                <div class="dash-head">
                    <div>
                        <div class="dash-hello">${hello}!</div>
                        <h1>MrPrompt Server 4.0</h1>
                        <div class="dash-meta">${d.total} projektów w htdocs · <span style="text-transform:capitalize">${new Date().toLocaleDateString('pl-PL', { weekday: 'long', day: 'numeric', month: 'long' })}</span></div>
                    </div>
                    <div class="dash-side">
                        <div class="dash-status">
                            ${chip(s.apache, 'Apache')}${chip(s.mysql, 'MariaDB')}
                            <span class="dash-chip"><i class="fa-brands fa-php"></i> ${escapeHtml(s.php)}</span>
                            <span class="dash-chip" title="Wolne: ${s.disk_free_gb} GB"><i class="fa-solid fa-hard-drive"></i> ${s.disk_used}%</span>
                        </div>
                        <button class="dash-search" onclick="openPalette()"><i class="fa-solid fa-magnifying-glass"></i> Szukaj projektu lub aplikacji <kbd>Ctrl K</kbd></button>
                    </div>
                </div>
                ${dashSection('fa-solid fa-bolt', 'Wróć do pracy', '', `<div class="dash-cards">${recent}</div>`, 'full')}
                <div class="dash-cols">
                    ${dashSection('fa-solid fa-list-check', 'Zadania do zrobienia', d.todos_total || '', `<div class="dash-scroll">${todoBody}</div>`)}
                    ${dashSection('fa-solid fa-broom', 'Do uporządkowania', d.attention_total > d.attention.length ? d.attention_total : '', `<div class="dash-scroll">${attBody}</div>`)}
                    ${dashSection('fa-solid fa-star', 'Ulubione i przypięte', '', favBody)}
                    ${dashSection('fa-solid fa-clock-rotate-left', 'Ostatnio zmieniane pliki', '', `<div class="dash-scroll">${filesBody}</div>`)}
                </div>
            </div>`;
        }

        function goHome() {
            const main = document.getElementById('mainContent');
            currentPath = '';
            if (DASH) renderDashboard(); // od razu stare dane, poniżej odświeżenie
            else {
                main.removeAttribute('style');
                main.innerHTML = '<div style="padding:40px; color:var(--text-muted);"><i class="fa-solid fa-spinner fa-spin"></i> Ładowanie pulpitu…</div>';
            }
            const snapshot = main.innerHTML;
            return fetch('mrprompt_server/dashboard.php')
                .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                .then(d => {
                    DASH = d;
                    if (typeof renderTaskbar === 'function') renderTaskbar();
                    // nie nadpisuj widoku, jeśli użytkownik zdążył wejść gdzie indziej
                    if (main.innerHTML !== snapshot) return;
                    renderDashboard();
                })
                .catch(() => {
                    if (!DASH) main.innerHTML = '<h1 style="color:var(--accent); font-size:2.4rem; font-weight:600;">MrPrompt Server 4.0</h1><p style="color:var(--text-muted);">Wybierz projekt z listy, aby zarządzać jego dokumentacją.</p>';
                });
        }

        // Kliki na pulpicie (delegacja)
        document.getElementById('mainContent').addEventListener('click', (e) => {
            const w = e.target.closest('.file-web');
            if (w) { e.preventDefault(); e.stopPropagation(); window.open(w.dataset.web, '_blank', 'noopener'); }
        }, true);
        document.getElementById('mainContent').addEventListener('click', (e) => {
            const o = e.target.closest('[data-act-dash="open"]');
            if (o) { e.preventDefault(); showProjectDetails(o.dataset.path, o.dataset.name); }
        });
        document.getElementById('mainContent').addEventListener('change', (e) => {
            const c = e.target.closest('[data-todo-path]');
            if (!c) return;
            const fd = new FormData();
            fd.append('action', 'toggle_todo');
            fd.append('path', c.dataset.todoPath);
            fd.append('idx', c.dataset.todoIdx);
            fetch('mrprompt_server/dashboard.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (d.status !== 'ok') throw new Error();
                    c.closest('.todo-line').classList.add('done');
                    setTimeout(() => { if (document.querySelector('.dash')) goHome(); }, 700);
                })
                .catch(() => { c.checked = false; showToast('Nie udało się zapisać zadania', 'error'); });
        });

        // --- PALETA POLECEŃ (Ctrl+K) ---
        const palette = document.getElementById('palette');
        const palInput = document.getElementById('palInput');
        const palList = document.getElementById('palList');
        let palItems = [], palIdx = 0;

        const TOOLS = [
            { t: 'Statystyki Serwera', i: 'fa-solid fa-chart-simple', run: () => loadServerStats() },
            { t: 'Diagnostyka: logi błędów', i: 'fa-solid fa-bug', run: () => openDiag('logs') },
            { t: 'Diagnostyka: porty i procesy', i: 'fa-solid fa-network-wired', run: () => openDiag('ports') },
            { t: 'Diagnostyka: zrzuty baz danych', i: 'fa-solid fa-database', run: () => openDiag('db') },
            { t: 'Nowy projekt', i: 'fa-solid fa-wand-magic-sparkles', run: () => diagNewProject() },
            { t: 'Czytnik MD', i: 'fa-brands fa-markdown', run: () => loadCzytnikMD() },
            { t: 'Czytnik JSON', i: 'fa-solid fa-code', run: () => loadCzytnikJSON() },
            { t: 'Strona startowa', i: 'fa-solid fa-house', run: () => goHome() },
        ];

        function openPalette() {
            palette.classList.add('active');
            palInput.value = '';
            renderPalette();
            setTimeout(() => palInput.focus(), 20);
            if (!DASH) goHome(); // dociągnij listę projektów
        }
        function closePalette() { palette.classList.remove('active'); }

        function renderPalette() {
            const q = palInput.value.trim().toLowerCase();
            const items = [];
            TOOLS.forEach(x => items.push({ kind: 'Narzędzie', icon: x.i, title: x.t, run: x.run }));
            APPS.forEach(a => items.push({ kind: 'Aplikacja', icon: a.icon, title: a.name, sub: a.url, run: () => window.open(a.url, '_blank', 'noopener') }));
            ((DASH && DASH.names) || []).forEach(p => items.push({ kind: 'Projekt', icon: 'fa-solid fa-folder', title: p.name, run: () => showProjectDetails(p.path, p.name) }));
            palItems = (q ? items.filter(x => (x.title + ' ' + (x.sub || '')).toLowerCase().includes(q)) : items.filter(x => x.kind !== 'Projekt'))
                .sort((a, b) => q ? (a.title.toLowerCase().startsWith(q) ? 0 : 1) - (b.title.toLowerCase().startsWith(q) ? 0 : 1) : 0)
                .slice(0, 30);
            palIdx = 0;
            palList.innerHTML = palItems.length ? palItems.map((x, i) => `
                <div class="pal-row ${i === 0 ? 'sel' : ''}" data-i="${i}">
                    <i class="${escapeHtml(x.icon)}"></i><span class="pt">${escapeHtml(x.title)}</span>
                    <span class="ps">${escapeHtml(x.sub || '')}</span><span class="pk">${x.kind}</span></div>`).join('')
                : '<div class="dash-empty">Brak wyników.</div>';
        }
        function palRun(i) {
            const it = palItems[i];
            if (!it) return;
            closePalette();
            it.run();
        }
        palInput.addEventListener('input', renderPalette);
        palInput.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                if (!palItems.length) return;
                palIdx = (palIdx + (e.key === 'ArrowDown' ? 1 : -1) + palItems.length) % palItems.length;
                palList.querySelectorAll('.pal-row').forEach((r, i) => r.classList.toggle('sel', i === palIdx));
                palList.querySelector('.sel').scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'Enter') palRun(palIdx);
            else if (e.key === 'Escape') closePalette();
        });
        palList.addEventListener('click', (e) => {
            const r = e.target.closest('.pal-row');
            if (r) palRun(+r.dataset.i);
        });
        palette.addEventListener('click', (e) => { if (e.target === palette) closePalette(); });
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                palette.classList.contains('active') ? closePalette() : openPalette();
            }
        });

        // --- DOLNY PASEK STANU ---
        const tbCtx = document.getElementById('tbCtx');
        const tbRight = document.getElementById('tbRight');
        let tbStatus = null, tbCtxPath = '', tbWasMysql = null;

        function setCtx(label, path) {
            tbCtxPath = path || '';
            tbCtx.classList.toggle('on', !!label);
            tbCtx.innerHTML = label ? escapeHtml(label) : '';
            tbCtx.title = path ? path + '  (kliknij, aby skopiować)' : '';
            tbCtx.style.cursor = path ? 'pointer' : 'default';
        }
        tbCtx.addEventListener('click', async () => {
            if (!tbCtxPath) return;
            try { await navigator.clipboard.writeText(tbCtxPath.replace(/\//g, '\\')); showToast('Skopiowano ścieżkę', 'success'); }
            catch (e) { showToast('Schowek zablokowany przez przeglądarkę', 'error'); }
        });

        function renderTaskbar() {
            const s = tbStatus, d = (typeof DASH !== 'undefined') ? DASH : null;
            let h = '';
            if (s) {
                h += `<button class="tb-chip ${s.mysql ? '' : 'bad'}" onclick="loadServerStats()" title="Stan usług: kliknij, aby otworzyć statystyki serwera"><i class="tb-dot"></i>Apache<i class="tb-dot ${s.mysql ? '' : 'off'}"></i>${s.mysql ? 'MariaDB' : 'MariaDB wyłączona'}</button>`;
                if (!s.mysql) h += `<button class="tb-chip act" onclick="diagStartMysql()" title="Uruchom MariaDB bez otwierania panelu XAMPP"><i class="fa-solid fa-play"></i>Uruchom</button>`;
                h += `<span class="tb-chip tb-php" title="Wersja PHP"><i class="fa-brands fa-php"></i>${escapeHtml(s.php)}</span>`;
                const cls = s.disk_used >= 95 ? 'bad' : s.disk_used >= 90 ? 'warn' : '';
                h += `<span class="tb-chip tb-disk ${cls}" title="Dysk zajęty w ${s.disk_used}%"><i class="fa-solid fa-hard-drive"></i>${s.disk_free_gb} GB wolne</span>`;
            }
            if (d && d.todos_total) h += `<button class="tb-chip" onclick="goHome()" title="Otwarte zadania ze wszystkich projektów"><i class="fa-solid fa-list-check"></i>${d.todos_total}</button>`;
            if (d && d.recent && d.recent[0]) {
                const p = d.recent[0];
                h += `<button class="tb-chip tb-recent" title="Ostatnio zmieniany projekt: kliknij, aby otworzyć" data-path="${escapeHtml(p.path)}" data-name="${escapeHtml(p.name)}"><i class="fa-regular fa-clock"></i>${escapeHtml(p.name)} · ${timeAgo(p.mtime)}</button>`;
            }
            h += '<span class="tb-hint"><kbd>Ctrl K</kbd>szukaj<kbd>F1</kbd>pomoc</span>';
            tbRight.innerHTML = h;
        }
        tbRight.addEventListener('click', (e) => {
            const r = e.target.closest('.tb-recent');
            if (r) showProjectDetails(r.dataset.path, r.dataset.name);
        });

        function pollStatus() {
            if (document.hidden) return;
            fetch('mrprompt_server/status.php').then(r => r.json()).then(s => {
                if (tbWasMysql === true && !s.mysql) showToast('MariaDB przestała odpowiadać: uruchom ją w panelu XAMPP', 'error');
                if (tbWasMysql === false && s.mysql) showToast('MariaDB znów działa', 'success');
                tbWasMysql = s.mysql;
                tbStatus = s;
                renderTaskbar();
            }).catch(() => {});
        }
        setInterval(pollStatus, 30000);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) pollStatus(); });
        pollStatus();

        // kontekst bieżącego widoku w pasku (opakowanie funkcji nawigacyjnych)
        (function () {
            const wrap = (name, label) => {
                const orig = window[name];
                window[name] = function () {
                    const r = orig.apply(this, arguments);
                    const l = typeof label === 'function' ? label.apply(null, arguments) : label;
                    if (l) setCtx(l[0], l[1]); else setCtx('');
                    return r;
                };
            };
            wrap('showProjectDetails', (p, n) => ['Projekt › ' + n, p]);
            wrap('loadServerStats', ['Statystyki serwera', '']);
            wrap('loadCzytnikMD', ['Czytnik MD', '']);
            wrap('loadCzytnikJSON', ['Czytnik JSON', '']);
            wrap('loadHelp', ['Pomoc', '']);
            wrap('goHome', null);
        })();

        // Start: pokaż pulpit po załadowaniu
        goHome();

        // --- POMOC ---
        function loadHelp(anchor) {
            const main = document.getElementById('mainContent');
            main.style.padding = '0';
            main.style.alignItems = 'stretch';
            main.style.justifyContent = 'stretch';
            main.innerHTML = '<iframe src="mrprompt_server/pomoc.html' + (anchor ? '#' + anchor : '') + '" style="width: 100%; height: 100%; border: none; background: var(--bg-dark);" title="Pomoc"></iframe>';
        }
        document.addEventListener('keydown', (e) => {
            if (e.key === 'F1') { e.preventDefault(); loadHelp(); }
        });

        // --- CZYTNIK MD LOGIC ---
        function loadCzytnikMD() {
            const main = document.getElementById('mainContent');
            main.style.padding = '0';
            main.style.alignItems = 'stretch';
            main.style.justifyContent = 'stretch';
            main.innerHTML = `<iframe src="mrprompt_server/czytnikmd.php" style="width: 100%; height: 100%; border: none; background: var(--bg-dark);"></iframe>`;
        }
        // --- CZYTNIK JSON LOGIC ---
        function loadCzytnikJSON() {
            const main = document.getElementById('mainContent');
            main.style.padding = '0';
            main.style.alignItems = 'stretch';
            main.style.justifyContent = 'stretch';
            main.innerHTML = `<iframe src="mrprompt_server/edytor_json/index.php" style="width: 100%; height: 100%; border: none; background: var(--bg-dark);"></iframe>`;
        }
    </script>
    <script src="mrprompt_server/diag.js"></script>
</body>

</html>
