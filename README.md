# melody
Easy web installer for redistribuing Symfony projects.

-

Melody is a simple web installer for deploying and delivering a Symfony project to people who don't want or can't execute manual installation.

## How to use

All explanation are listed in the first page of the installer. Just follow them and change everything you want. But if you want a quick tour, here are the short ones:

### Creating the installer
- Put a zip with your project in the ```resources``` folder (the zip must not include the vendors or the ```app/config/parameters.yml``` file).
- Include a SQL dump if you want
- List all your requirements (PHP, Apache, mods, etc..) in the configuration file
- Change whatever you want (if you want to)
- Zip everything
- Distribute your awsome installer

### Using the installer
- Unzip in ```/var/www/my_website/web/melody```
- Go to http://my_website/melody
- Install
- Enjoy
