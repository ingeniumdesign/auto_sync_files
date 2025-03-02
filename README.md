# Auto Sync Files

**TYPO3 12.4.x - Auto Sync Files Extension**

This extension periodically downloads external files via the Scheduler to your local webspace so that you always have the newest version available. It is especially useful for caching external resources and improving performance. Additionally, the extension now offers an optional **Download & Extract** mode which downloads a compressed archive (ZIP/TAR) and extracts its contents, replacing existing files.

---

## Features

- **Download Only:** Download an external file and store it locally.
- **Download & Extract:** Download a compressed archive from a remote URL, extract it, and replace existing files/folders.
- **Optional Cache Clearing:** Clear the TYPO3 frontend cache after a file change.
- **Flexible Configuration:** Configure the download URL, local path, and cache clearing option via the TYPO3 Scheduler backend.

---

## Example: Google Analytics Code

### Setup for Download Only Mode

1. **Add a new Scheduler Task:**  
   In the TYPO3 backend, go to the Scheduler and add a new task.  
   Select **"Auto Sync Files: Download Only"** as the task type.

2. **Configure the Task:**
  - **Download URL:** Set this to the URL of the external file.  
    _Example:_ `https://www.google-analytics.com/analytics.js`
  - **Local Path:** Set this to the absolute path where the file should be stored.  
    _Example:_ `/html/typo3/fileadmin/Templates/Assets/JavaScript/analytics.js`
  - **Clear Cache:** Tick the checkbox if you want the frontend cache to be cleared when a new version is detected (cache is only cleared if the downloaded file differs from the stored file).

3. **Include the Synced File in TypoScript:**
   ```typoscript
   includeJSFooter.googleAnalytics = fileadmin/Templates/Assets/JavaScript/analytics.js
   ```

4. **Modify the Google Analytics Code:**  
   Replace the default Google Analytics snippet with one that loads the local file instead of fetching the external library:
   ```javascript
   (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
       (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();
   })(window,document,'script','','ga');
   ga('set', 'anonymizeIp', true);
   ga('create', 'UA-XXXXXX-1', 'auto'); // Replace with your Tracking ID
   ga('send', 'pageview');
   ```

### Setup for Download & Extract Mode

1. **Add a new Scheduler Task:**  
   In the TYPO3 backend, add a new task and select **"Auto Sync Files: Download & Extract"** as the task type.

2. **Configure the Task:**
  - **Download URL:** Set this to the URL of the compressed archive (ZIP or TAR) that contains the files.
  - **Local Path:** Specify the target folder where the archive should be extracted.  
    **Warning:** All files in the specified folder will be deleted before the new files are copied over.
  - **Clear Cache:** Enable the option if you want to clear the frontend cache after the update.

---

## How It Works

- **Download Only Task:**  
  Downloads the specified external file. If a new version is detected, it replaces the old file and optionally clears the cache.

- **Download & Extract Task:**  
  Downloads a compressed archive, extracts its contents to a temporary directory, then deletes the existing target folder and copies the extracted files over.

---

## Important Notes

- **Data Loss Warning:**  
  When using the Download & Extract mode, the entire contents of the specified local folder will be deleted before the new files are placed.  
  **Do not set the Local Path to the root of critical directories like `/fileadmin/`** — only use dedicated subfolders.

- **Configuration:**  
  Both task types can be configured via the TYPO3 Scheduler. Ensure you provide valid and complete information in the input fields.

---

## Contact & Communication

### GitHub

[Auto Sync Files on GitHub](https://github.com/ingeniumdesign/auto_sync_files/)

### Agency

**INGENIUMDESIGN**  
TYPO3 – Internetagentur  
65510 Idstein  
[https://www.ingeniumdesign.de/](https://www.ingeniumdesign.de/)  
info@ingeniumdesign.de

### Donate

- **Amazon:** [Amazon Wishlist](https://www.amazon.de/hz/wishlist/ls/13RT2BFNRP05)
- **PayPal:** [paypal.me/INGENIUMDESIGN](https://www.paypal.me/INGENIUMDESIGN/)

---

## Live References

We are searching for live references or live examples of the Auto Sync Files Extension. Please contact us if you're using it!

**Links/References:**  
[https://www.easy-sprachreisen.de/](https://www.easy-sprachreisen.de/) – by INGENIUMDESIGN
