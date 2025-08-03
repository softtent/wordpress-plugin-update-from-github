# WordPress Plugin Auto Update from GitHub

A lightweight and developer-friendly class to enable automatic plugin updates in WordPress directly from a GitHub repository.

---

## 🔧 Features

- Checks GitHub Releases for new plugin versions
- Compares current plugin version with the latest GitHub tag
- Downloads and installs ZIP directly from GitHub
- Automatically renames the plugin folder after download
- Displays changelog from the GitHub release body
- Supports GitHub Personal Access Tokens (for private repos)

---

## 📦 Installation

1. Clone or download this repository:

    ```bash
    git clone https://github.com/therakib7/wordpress-plugin-update-from-github.git
    ```

2. Include the `AutoUpdate` class in your plugin.

3. Initialize the updater with your plugin configuration:

    ```php
    use SoftTent\AutoUpdate;

    new AutoUpdate([
        'plugin_file'       => 'my-plugin-folder',    // Plugin directory name
        'plugin_slug'       => 'my-plugin-main-file', // Main plugin file (without .php)
        'github_author'     => 'your-github-username',
        'github_repository' => 'your-plugin-repo',
        'access_token'      => 'your-github-token' // Optional
    ]);
    ```

> 🔐 If your repository is private, a [GitHub Personal Access Token (PAT)](https://github.com/settings/tokens) is required.

---

## 🧠 How It Works

1. **Checks for Updates:**
   Hooks into `pre_set_site_transient_update_plugins` to fetch the latest release using the GitHub API.

2. **Version Comparison:**
   Uses `version_compare()` to determine if an update is available based on GitHub release tags.

3. **Authenticated Download:**
   Adds an authorization header using `http_request_args` to download the ZIP file from GitHub, including private repos.

4. **Folder Rename:**
   Uses `upgrader_source_selection` to rename GitHub’s auto-generated folder (e.g., `author-repo-hash`) to your correct plugin directory name.

5. **Changelog Display:**
   Hooks into `plugins_api` to display release notes or changelog from the GitHub release body in the WordPress plugin update screen.

---

## ✅ Requirements

- WordPress 5.0+
- PHP 7.4+

---

## 🔐 Security Notes

- Do not expose your access token publicly.
- All input is internally validated and sanitized.
- Or Create a access token only for specific repository with read only permission
---

## 📃 License

This project is open-sourced under the MIT License.

---

## 🙋‍♂️ Author

Developed by [@therakib7](https://github.com/therakib7)
If this helped you, consider giving the repository a ⭐️!
