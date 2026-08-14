# Redirect Fixer Component
A Joomla! component for automatically scanning and updating internal article links based on your site's defined redirects.

If you have outdated internal links in your Joomla! articles after re-naming article aliases or menu items, this component will fix your links inside articles automatically. The Redirect Fixer component scans your article content, identifies URLs that match your site's published redirects, and allows you to easily update them to the new, updated URLs.

## How To Use Redirect Fixer Component
1.  Install the latest release via the Joomla! Extensions Manager from this URL: [https://github.com/brettvac/redirectfixer/releases/latest/download/com_redirectfixer.zip](https://github.com/brettvac/redirectfixer/releases/latest/download/com_redirectfixer.zip)
2.  Navigate to **Components > Redirect Fixer** in the Joomla! Administrator menu.
3.  Click **"Scan for Affected Articles"** to analyze your article content against your configured redirects.
4.  On the results page, click **"Fix Selected Articles"** to correct one article at a time or **"Fix All Articles"** to batch apply the changes to your content.

## Features
* **Seamless Joomla! Redirects Integration**. The component uses your existing redirect rules managed in Joomla!'s built-in Redirect Component to scan and update articles.
* **Granular Control**: Provides a clear overview of redirected links inside articles and their updated URLs, allowing you to review and choose which articles to update.
* **Comprehensive Article Content Scan**. The component scans both the introduction (`introtext`) and full article content (`fulltext`) of all published Joomla! articles for URLs that contain redirects.
* **Secure Content Updates**: Respects article checkout/check-in status, and maintains database integrity.

## Requirements
This component works with Joomla! versions **4.4 or later** and requires **PHP 7.2.5 or later**.

## FAQ
**Q: Will this component fix redirects for external websites or third-party component URLs?**
**A:** No, the component specifically focuses on **internal links** within your Joomla! site's articles that correspond to redirects you've set up *for your own domain* using the internal Joomla! component.

**Q: Can it fix URLs in modules, custom HTML, or content fields outside of articles?**
**A:** Currently, it's designed to scan and update URLs exclusively within the `introtext` and `fulltext` fields of Joomla! articles (`#__content` table). Other content areas are not covered.

**Q: This component is awesome! Can I send a donation?**  
**A:** Sure! Send your cryptonation to the following wallets:

`BTC 1PXWZJcBfehqgV25zWdVDS6RF2yVMxFkZD`

`Eth 0xC9b695D4712645Ba178B4316154621B284e2783D`
