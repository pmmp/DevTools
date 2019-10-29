# Development Tools <em>for PocketMine-MP</em>

## Usage
* _/makeplugin \<pluginName\>_: Creates a Phar plugin archive for its distribution
* _/makeplugin *_: Creates Phar plugin archives for all loaded plugins
* _/makeserver_: Creates a PocketMine-MP Phar archive
* _/checkperm \<node\> [playerName]_: Checks a permission node

## Using ConsoleScript to build a DevTools phar from source code
Contrary to popular assumption, this is very simple. Assuming you have a php executable in your PATH variable, cd into the DevTools directory (the folder where plugin.yml is located) and simply run the following:
```
php -dphar.readonly=0 path/to/ConsoleScript.php --make path/to/DevTools --relative path/to/DevTools --out path/to/put/devtools/phar/in/DevTools.phar
```
You can then load the phar onto a PocketMine-MP server. A correctly-built DevTools phar can also be executed directly from the command line as if it was the ConsoleScript.

## Build phars from the command line
You can also use the ConsoleScript or a DevTools phar from the command-line to build PocketMine-MP phars or plugin phars.

The script currently takes the following arguments:
- `--make`: The path to the files you want to bundle into a phar
- `--relative`: (Optional) Relative path to use when building the phar. This usually isn't necessary for plugins. Used to build PocketMine-MP phars with the `src` directory without including the files in the repository root.
- `--entry`: (Optional) PHP file within the phar to execute when running the phar from the command-line. Usually not needed for plugins, but required for a PocketMine-MP phar. Used to generate phar stubs.
- `--stub`: (Optional) PHP file to use as a custom phar stub. The stub will be executed when the phar is run from the command line. 
- `--out`: Path and filename of the output phar file.

Example command line for building a plugin:
```
php -dphar.readonly=0 path/to/ConsoleScript.php --make path/to/your/plugin/sourcecode --out path/to/put/your/plugin.phar
```

Example command line for building PocketMine-MP
```
php -dphar.readonly=0 path/to/ConsoleScript.php --make path/to/PocketMine-MP/src,path/to/PocketMine-MP/vendor --relative path/to/PocketMine-MP --entry path/to/PocketMine-MP/src/pocketmine/PocketMine.php --out path/to/put/your/PocketMine-MP.phar
```
**IMPORTANT - Don't forget to include the `vendor` directory when building PocketMine-MP!**
