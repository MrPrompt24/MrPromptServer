<div align="center">

# 🖥️ MrPrompt Server 4.0

**Panel zarządzania XAMPP / XAMPP management panel**

![PHP](https://img.shields.io/badge/PHP-8%2B-777BB4?logo=php&logoColor=white)
![XAMPP](https://img.shields.io/badge/XAMPP-Windows-FB7A24?logo=xampp&logoColor=white)
![SQLite](https://img.shields.io/badge/SQLite-local-003B57?logo=sqlite&logoColor=white)
![Status](https://img.shields.io/badge/status-4.0%20stable-brightgreen)
![Local only](https://img.shields.io/badge/use-localhost%20only-orange)

🇵🇱 [Polski](#-polski) · 🇬🇧 [English](#-english)

</div>

---

## 🇵🇱 Polski

### 📖 Opis

**MrPrompt Server** to lokalny panel do zarządzania projektami w folderze `htdocs` środowiska XAMPP. Zamiast przeszukiwać katalogi ręcznie, dostajesz jedno miejsce z drzewem projektów, edytorem dokumentacji, diagnostyką serwera i narzędziami deweloperskimi.

### ✨ Funkcje

- 🚀 **Menu „Aplikacje”**: własna lista aplikacji z wyszukiwarką, przypinaniem i edycją; kliknięcie otwiera aplikację i zamyka menu
- 🌳 **Drzewo projektów**: przeglądanie folderów w `htdocs`, ulubione (⭐) i informacje o projekcie
- 📝 **Edytor** dokumentacji projektu
- 📄 **Czytnik MD** i 🧾 **Czytnik JSON**
- 📊 **Statystyki serwera** i statystyki plików według technologii
- 🩺 **Diagnostyka**: logi błędów, porty i procesy, bazy danych
- 🔒 **Audyt bezpieczeństwa projektu** przed publikacją (wykrywa m.in. klucze w kodzie, zrzuty baz, `phpinfo`)
- 💻 **CMD** wbudowany w panel
- 🗄️ **Menedżer baz IndexedDB**
- ❓ **Pomoc** pod klawiszem `F1`

### 🧰 Wymagania

- 🪟 Windows
- 🟠 [XAMPP](https://www.apachefriends.org/) (Apache + PHP 8 lub nowszy)
- 🗃️ Rozszerzenie PHP `pdo_sqlite` (domyślnie włączone w XAMPP)
- 🌐 Nowoczesna przeglądarka

### 📦 Instalacja

1. Sklonuj repozytorium do folderu `htdocs`:
   ```bash
   cd C:\xampp\htdocs
   git clone https://github.com/MrPrompt24/MrPromptServer.git
   ```
2. Uruchom **Apache** w panelu XAMPP.
3. Otwórz w przeglądarce:
   ```
   http://localhost/MrPromptServer/
   ```

Lokalna baza `mrprompt_server/apps.db` (lista aplikacji i ulubione) tworzy się automatycznie przy pierwszym uruchomieniu i nie jest częścią repozytorium.

### 🗂️ Struktura

```
.
├── index.php              # panel główny
└── mrprompt_server/
    ├── edytor/            # edytor dokumentacji
    ├── edytor_json/       # czytnik JSON
    ├── menager_baz_idexdb # menedżer baz IndexedDB
    ├── *_handler.php      # punkty końcowe (AJAX)
    ├── security.php       # ochrona przed CSRF
    └── db_init.php        # inicjalizacja bazy SQLite
```

### 🔐 Bezpieczeństwo

> ⚠️ **Ta aplikacja jest przeznaczona wyłącznie do pracy lokalnej (`localhost`).**
> Zawiera funkcje uruchamiania poleceń systemowych i zapisu plików. **Nie wystawiaj jej do internetu** ani nie wgrywaj na publiczny hosting bez dodatkowego uwierzytelniania.

- 🛡️ Zapytania zmieniające stan wymagają tokenu sesji i zgodnego nagłówka `Origin` (ochrona przed CSRF), a ciasteczko sesji ma `SameSite=Strict`.
- 🔎 Znalazłeś lukę? Zgłoś ją prywatnie przez zakładkę **Security → Report a vulnerability** w tym repozytorium, zamiast publicznego zgłoszenia.

### 🤝 Współpraca

Pull requesty i zgłoszenia (**Issues**) są mile widziane. Przy większych zmianach najpierw otwórz zgłoszenie z opisem pomysłu.

### 📄 Licencja

Licencja nie została jeszcze określona. Do czasu jej dodania obowiązuje domyślnie pełna ochrona praw autorskich autora.

### 👤 Autor

**MrPrompt** · 🌐 [mrprompt.eu](https://mrprompt.eu/) · 🐙 [@MrPrompt24](https://github.com/MrPrompt24)

---

## 🇬🇧 English

### 📖 Description

**MrPrompt Server** is a local panel for managing projects in the `htdocs` folder of a XAMPP environment. Instead of browsing directories by hand, you get one place with a project tree, a documentation editor, server diagnostics, and developer tools.

### ✨ Features

- 🚀 **“Applications” menu**: your own app list with search, pinning and editing; clicking an app opens it and closes the menu
- 🌳 **Project tree**: browse folders in `htdocs`, favorites (⭐) and project info
- 📝 **Editor** for project documentation
- 📄 **Markdown reader** and 🧾 **JSON reader**
- 📊 **Server statistics** and file statistics by technology
- 🩺 **Diagnostics**: error logs, ports and processes, databases
- 🔒 **Project security audit** before publishing (detects e.g. keys in code, database dumps, `phpinfo`)
- 💻 Built-in **CMD**
- 🗄️ **IndexedDB manager**
- ❓ **Help** under the `F1` key

### 🧰 Requirements

- 🪟 Windows
- 🟠 [XAMPP](https://www.apachefriends.org/) (Apache + PHP 8 or newer)
- 🗃️ PHP `pdo_sqlite` extension (enabled by default in XAMPP)
- 🌐 A modern web browser

### 📦 Installation

1. Clone the repository into `htdocs`:
   ```bash
   cd C:\xampp\htdocs
   git clone https://github.com/MrPrompt24/MrPromptServer.git
   ```
2. Start **Apache** in the XAMPP control panel.
3. Open in your browser:
   ```
   http://localhost/MrPromptServer/
   ```

The local database `mrprompt_server/apps.db` (app list and favorites) is created automatically on first run and is not part of the repository.

### 🗂️ Structure

```
.
├── index.php              # main panel
└── mrprompt_server/
    ├── edytor/            # documentation editor
    ├── edytor_json/       # JSON reader
    ├── menager_baz_idexdb # IndexedDB manager
    ├── *_handler.php      # endpoints (AJAX)
    ├── security.php       # CSRF protection
    └── db_init.php        # SQLite initialization
```

### 🔐 Security

> ⚠️ **This application is intended for local use only (`localhost`).**
> It includes features that run system commands and write files. **Do not expose it to the internet** or deploy it to public hosting without additional authentication.

- 🛡️ State-changing requests require a session token and a matching `Origin` header (CSRF protection), and the session cookie uses `SameSite=Strict`.
- 🔎 Found a vulnerability? Please report it privately via **Security → Report a vulnerability** in this repository instead of opening a public issue.

### 🤝 Contributing

Pull requests and **Issues** are welcome. For larger changes, please open an issue first to discuss your idea.

### 📄 License

No license has been specified yet. Until one is added, all rights are reserved by the author by default.

### 👤 Author

**MrPrompt** · 🌐 [mrprompt.eu](https://mrprompt.eu/) · 🐙 [@MrPrompt24](https://github.com/MrPrompt24)
