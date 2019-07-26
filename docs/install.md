# How to install Chassé

This page details how to install Chassé. Apart from recommending the Bootstrap
theme, there's **nothing special here**; these instructions are generic CiviCRM
extension instllation instructions.

## Requirements

* PHP v7+
* CiviCRM (*5*.x)
* Not required but highly recommended [Shoreditch theme](https://civicrm.org/extensions/shoreditch) - Chassé looks a bit weird without it.

## Installation (Web UI)

This extension has not yet been published for installation via the web UI.

## Installation (CLI, Zip)

Sysadmins and developers may download the `.zip` file for this extension and
install it with the command-line tool [cv](https://github.com/civicrm/cv).

```bash
cd <extension-dir>
cv dl chasse@https://github.com/artfulrobot/chasse/archive/master.zip
```

## Installation (CLI, Git)

Sysadmins and developers may clone the [Git](https://en.wikipedia.org/wiki/Git) repo for this extension and
install it with the command-line tool [cv](https://github.com/civicrm/cv).

```bash
git clone https://github.com/artfulrobot/chasse.git
cv en chasse
```
