# Unreal Tournament Stats Tracker
## Disclaimer
This is an excercise project on data collection and analysis I made 
between 2014-2016.

The purpose of this repo is strictly archival. 
**I strongly recommend against running it in production.** 
There are probably tons of bugs and vulnerabilities present
due to usage of ancient coding patterns. I cannot guarantee
this will run in any modern environment.

## About
Unreal Tournament Stats Tracker allows you to view various statistics 
about players and servers. Also, it presents some detailed informations 
about maps, including automatically generated map layouts. 
The site used to be running at the address http://tracker.ut99.tk

![A site with huge logo, and a list of game servers](Screenshot-exemple/fssRERg.jpg)

## Requirements
### Web frontend
- Apache 2.4
- PHP >= 5.3, with extensions:
  - pdo
  - mbstring
  - gd
- MySQL or MariaDB
  - To get the Server Scanner to work, MySQL >= 8.0 require changing user auth to `mysql_native_password` [(info)](https://dev.mysql.com/doc/refman/8.4/en/caching-sha2-pluggable-authentication.html)

### Server scanner
- Windows
- .NET Framework Runtime 4

### Map downloader
If you want to run Map Downloader, you'll also need:
- Windows VM with UT installation
- PHP configured for CLI usage, with curl extension


## Installation
Create a dedicated database on your MySQL/MariaDB server. This can be done easily with a tool like [PHPMyAdmin](https://github.com/phpmyadmin). I'm using name `utt` in the example. 

Additionaly, add new user that will be accessing the database. In my example, `uttWeb`.

**It's a good practice to create different database users for frontend, scanner, and map downloader.**
Web frontend user only needs modification permissions for tables:
- mapinfo: INSERT, UPDATE
- mapdownloadqueue: INSERT
- serverqueue: INSERT
**For the time of installation, CREATE TABLE permission should also be granted to web frontend user.**

Permissions needed for Map Downloader:
- mapdownloadqueue: SELECT, DELETE

### Configuration files
Edit config files for your instance. Each component has its own separate file with configuration:
- Web frontend:
  `/appConfig.php` (remove .dist extension)
- Server scanner: 
  `/Scanner/bin/[buildType]/utt_updater3.ini` (remove .dist extension)
- UT Map Downloader:
  `/UTMP/mapdlcron2.php`
- Wireframe renderer:
  `/WireframeRenderer/RendererConfig.php`

After you configure all components, run installer script. To create database structure, use /Installer/index.php script. This is initiated from web, just navigate to:

`https://<my-server>/Installer/index.php`

  
### Directories explained:
- Assets - static files (graphics, styles, js, etc)
- Installer - setup script
- Locale - language files
- N14Core = Namonaki14's Completely Oosless and Redundant Extensions
- N14Data = used by some of the generic purpose scripts
- N14Inc - additional includes
- Scanner - VB.NET server scanner and master server
- UTMDC = UT Map Download CSomething, database of map download links
- UTMP = UT Map Page content - screenshots, layouts, map reports, etc.
    Also, the directory of Map Downloader.
- WireframeRenderer - script creating map layout images from T3D files

