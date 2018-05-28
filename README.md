# auto_sync_files
TYPO3 8.7 Auto-Sync-Files Extension

This Extension downloads externals files periodically via Scheduler to your Webspace. Thus you do always have the newest version of a file locally stored. This can be really useful to improve performance because external files can be cached this way.
It also allows you to clear the cache everytime there is a new version of the file.


### Example with Google Analytics Code

#### Setup
* Go to Scheduler, add a new Task and chose "auto_file_sync".

* Set the frequency to any number you want (we recoment 3600 = one hour) and the description to something you can remember, e.g. "Google Analytics Downloader".

* Now set "The URL of the File that is supposed to be downloaded" to the destination of the Google Analytics-Script: https://www.google-analytics.com/analytics.js 

* In "The absolute path the file should be saved to" you fill in the local path where the Javascript-File should be saved to. E.g. "/html/typo3/fileadmin/Templates/Assets/JavaScript/analytics.js"

* If you want the frontend-cache to be cleared everytime there is a different version than the stored one, tik the checkbox "Clear Frontend-Cache after execution" (NOTICE: The cache is only cleared if there is a difference between the two files). 

* Include the locally-synced-file in your Typoscript

* Replace the default Google-Analytics-Code to one that does not load the external Library (you can place this bit in a Javascript-File of your choice). We just removed the part where it loads the external library from the original code by Google:
```javascript
// Google analytics like the original one
 (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
     (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();
     // This line is removed
 })(window,document,'script','','ga');
 ga('create', 'UA-XXXXXX-1', 'auto'); // Your tracking-ID 
 ga('send', 'pageview');
 ```