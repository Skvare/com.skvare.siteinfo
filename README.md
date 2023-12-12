# com.skvare.siteinfo

![Screenshot](/images/siteinfo_setting.png)

This extension allow to Collect System Status information in json format.
Information contain CiviCRM and Drupal 7, 8, 9, 10.

## Requirements

* PHP v7.2+
* CiviCRM

## Installation (Web UI)

Learn more about installing CiviCRM extensions in the [CiviCRM Sysadmin Guide](https://docs.civicrm.org/sysadmin/en/latest/customize/extensions/).

## Installation (CLI, Zip)

Sysadmins and developers may download the `.zip` file for this extension and
install it with the command-line tool [cv](https://github.com/civicrm/cv).

```bash
cd <extension-dir>
cv dl com.skvare.siteinfo@https://github.com/skvare/com.skvare.siteinfo/archive/master.zip
```

## Installation (CLI, Git)

Sysadmins and developers may clone the [Git](https://en.wikipedia.org/wiki/Git) repo for this extension and
install it with the command-line tool [cv](https://github.com/civicrm/cv).

```bash
git clone https://github.com/skvare/com.skvare.siteinfo.git
cv en siteinfo
```

## Getting Started

This extension required a centralized system that collects all sites.
information with different environments (development, staging, and production).

Store the data in the database and prepare the report per environment. This report, Repository, is available at: https://git.skvare.com/Core/sites-report-status

![Screenshot](/images/siteinfo_dashabord.png)
