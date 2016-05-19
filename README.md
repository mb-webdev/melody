# melody
Easy web installer for redistribuing Symfony projects.

---

Melody is a simple web installer for deploying and delivering a Symfony project to people who don't want or can't execute manual installation.

## How to create the installer

There is a lot of steps and options to configure to create the installer for your Symfony project, but the hardest part is already done. All you have to to is :

- providing the files (zip and, if you want, sql)
- configuring the file ```config.yml```

The first step may be easy, but the second one need some explanations. Here is a complete version of the config file :
```
archive:
    # Required
    # The name of the zip containing your Symfony project
    # and located in the 'resources' folder
    filename: 'archive.zip'

    # Optional, default : false
    # Indicate if the zip contains the vendors too
    # Required if the server can't execute shell, like a shared hosting
    contains_vendors: false

    # Optional, default : false
    # Indicate if the zip already contains the bundle's assets in /web
    # Required if the server can't execute shell, like a shared hosting
    contains_installed_assets: false

# Required
# The list of requirements (taken from the PHPinfo)
# Use names to clearly identify sections (like 'PHP' and 'Apache')
# Key is the path of the field to check
# Value is the required value
# use < or <= or > or >= to compare number and version number
# use ~ to find the value somewhere in the string
# Message is the error message to display if the requirement does not match
requirements:
    PHP:
        - {key: 'Core.PHP Version', value: '>=5.3', message: 'The PHP version must be 5.3 or upper'}
        - {key: 'Core.PHP Version', value: '<7', message: 'The PHP version must be lower than 7'}
        - {key: 'PDO.PDO support', value: 'enabled', message: 'PHP PDO module must be installed and enabled'}
        - {key: 'PDO.PDO drivers', value: '~mysql', message: 'PHP PDO module must support MySQL databases'}
        - {key: 'ctype.ctype functions', value: 'enabled', message: 'PHP ctype module must be installed and enabled'}
        - {key: 'json.json support', value: 'enabled', message: 'PHP json module must be installed and enabled'}
    Apache:
        - {key: 'apache2handler.Loaded Modules', value: '~mod_rewrite', message: 'Modue rewrite must be installed and enabled'}

# Optional, default : null
# The name of the sql file to import during the installation
# and located in the 'resources' folder
sql: 'dump.sql'

# Optional, default : null
# The list of bash commands to execute after the installation
after_install:
    - 'php bin/console assets:install --env=prod'
    - 'php bin/console assetic:dump --env=prod'

# Required
# The list of steps to display in the breadcrumbs
steps:
    - 'Start'
    - 'Checking'
    - 'Install'
    - 'Finalise'

# Required
# The title of the installer
title: "Melody Installer"

```

As you can see, there is a lot of options, but everything is in this only file, so take the time to clearly understand what every key exactly do.

Once everything is configured, don't forget to change the layout of the installer to match with your. You change anything you want. And if your are a developer, feel free to add/edit/remove steps in the process to match with your expectations. The ony thing you have to do is playing with the smalls php files at the root of the installer.

You can change the name of the installer's folder too. If you don't like ```my_site/melody``` you can rename to ```my_site/install``` or even ```my_site/whatever``` if you want to.

Once everything is OK, don't forget to play your installer to see if everything is OK, but ALWAYS BACKUP YOUR INSTALLER FIRST !

Now you can zip everything and spread your installer to the world.

### Using the installer as a client
- Unzip the content of the installer in ```/var/www/my_website/web/melody```
- Go to http://my_website/melody
- Install
- Enjoy
