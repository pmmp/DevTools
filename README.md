# Development Tools <em>for PocketMine-MP</em>

### Warning: This version is for the new PocketMine-MP API

	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU Lesser General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU Lesser General Public License for more details.

	You should have received a copy of the GNU Lesser General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.


## Installation
- Drop it into the PocketMine's `plugins/` folder.
- Restart the server. The plugin will be loaded

## Usage
* _/makeplugin <pluginName>_: Creates a Phar plugin archive for its distribution
* _/makeserver_: Creates a PocketMine-MP Phar archive
* _/checkperm <node> [playerName]_: Checks a permission node

## Create .phar from console
Download [DevTools.phar](https://github.com/PocketMine/DevTools/releases)

	php -dphar.readonly=0 DevTools.phar \
	--make="./plugin/" \
	--relative="./plugin/" \
	--out "plugin.phar"

or [ConsoleScript.php](https://github.com/PocketMine/DevTools/blob/master/src/DevTools/ConsoleScript.php)

	php -dphar.readonly=0 ConsoleScript.php \
	--make="./plugin/" \
	--relative="./plugin/" \
	--out "plugin.phar"
