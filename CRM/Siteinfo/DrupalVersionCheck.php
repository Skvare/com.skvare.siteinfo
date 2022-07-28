<?php

use CRM_Siteinfo_ExtensionUtil as E;

class CRM_Siteinfo_DrupalVersionCheck {
  /**
   * Project is missing security update(s).
   */
  const UPDATE_NOT_SECURE = 1;
  /**
   * Current release has been unpublished and is no longer available.
   */
  const UPDATE_REVOKED = 2;

  /**
   * Current release is no longer supported by the project maintainer.
   */
  const UPDATE_NOT_SUPPORTED = 3;

  /**
   * Project has a new release available, but it is not a security release.
   */
  const UPDATE_NOT_CURRENT = 4;

  /**
   * Project is up to date.
   */
  const UPDATE_CURRENT = 5;

  /**
   * Project's status cannot be checked.
   */
  const UPDATE_NOT_CHECKED = -1;

  /**
   * No available update data was found for project.
   */
  const UPDATE_UNKNOWN = -2;

  /**
   * There was a failure fetching available update data for this project.
   */
  const UPDATE_NOT_FETCHED = -3;

  /**
   * We need to (re)fetch available update data for this project.
   */
  const UPDATE_FETCH_PENDING = -4;

  /**
   * Maximum number of attempts to fetch available update data from a given host.
   */
  const UPDATE_MAX_FETCH_ATTEMPTS = 2;

  /**
   * Maximum number of seconds to try fetching available update data at a time.
   */
  const UPDATE_MAX_FETCH_TIME = 30;

  /*
   * Functin to check drupal version status.
   *
   * @param $drupalType
   * @param $version
   * @return array
   */
  function checkVersion($drupalType, $version) {
    $project = 'drupal';
    // Build current project details
    $projects = $this->currentProject($version);

    // get the xml details for release details.
    if (in_array($drupalType, ['Drupal8', 'Drupal9'])) {
      $url = 'https://updates.drupal.org/release-history/drupal/current';
    }
    else {
      $url = 'https://updates.drupal.org/release-history/drupal/7.x';
    }
    $xml = $this->getXmlFromUrl($url);
    $available = $this->updateParseXml($xml);
    $versionList = array_keys($available['releases']);
    $versionListDetail = [];
    foreach ($versionList as $versionNumber) {
      $versionNumberInt = intval($versionNumber);
      if (!array_key_exists($versionNumberInt, $versionListDetail)) {
        $versionListDetail[$versionNumberInt] = [];
      }
      $versionListDetail[$versionNumberInt][] = $versionNumber;
    }
    $this->calculateProjectUpdateStatus($projects[$project], $available);
    if (in_array($drupalType, ['Drupal8', 'Drupal9'])) {
      $latestVersion = $projects['drupal']['latest_version'];
    }
    else {
      $latestVersion = $projects['drupal']['latest_version'];
    }
    $isSecurityRelease = FALSE;
    $message = '';
    foreach ($projects[$project]['security updates'] as $securityupdate) {
      if (version_compare($version, $securityupdate['version']) < 0) {
        $isSecurityRelease = TRUE;
        $message .= PHP_EOL . 'Security update ' . $securityupdate['version'] . ' (' . date('Y-M-d', $securityupdate['date']) . ' )';
      }
    }
    $suggestedVersions = $this->getMoreRecommendedVersion($version,
      $versionListDetail);
    foreach ($suggestedVersions as $suggestedVersion) {
      $message .= PHP_EOL . 'Suggested version ' .
        $available['releases'][$suggestedVersion]['version'] . ' (' .
        date('Y-M-d', $available['releases'][$suggestedVersion]['date']) . ' )';
    }
    $projectStatus = [];
    if (version_compare($version, $latestVersion) < 0 || count
      ($suggestedVersions) > 1) {
      if (empty($message) && version_compare($version, $latestVersion) < 0) {
        $message = 'Drupal upgrade to ' . $latestVersion;
      }
      $projectStatus = [
        'isUpgradeRequire' => TRUE,
        'isSecurityRelease' => $isSecurityRelease,
        'latestVersion' => $latestVersion,
        'message' => $message];

      return $projectStatus;
    }

    $projectStatus = [
      'isUpgradeRequire' => FALSE,
      'isSecurityRelease' => FALSE,
      'latestVersion' => $latestVersion,
      'message' => $message];

    return $projectStatus;
  }

  public function getMoreRecommendedVersion($version, $versionListDetail) {
    $versionNumberInt = intval($version);
    unset($versionListDetail[$versionNumberInt]);
    $recommendedVersion = [];
    foreach ($versionListDetail as $primaryVersion => $versionList) {
      if ($primaryVersion > $versionNumberInt) {
        $recommendedVersion[] = $versionList[0];
      }
    }

    return $recommendedVersion;
  }

  /**
   * Function to get xml from url.
   *
   * @param $url
   * @return bool|string
   */
  public function getXmlFromUrl($url) {
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');

    $xmlstr = curl_exec($ch);
    curl_close($ch);

    return $xmlstr;
  }

  /**
   * Function to Parse the xml.
   *
   * @param $raw_xml
   * @return array|void
   */
  public function updateParseXml($raw_xml) {
    try {
      $xml = new SimpleXMLElement($raw_xml);
    }
    catch (Exception $e) {
      // SimpleXMLElement::__construct produces an E_WARNING error message for
      // each error found in the XML data and throws an exception if errors
      // were detected. Catch any exception and return failure (NULL).
      return;
    }
    // If there is no valid project data, the XML is invalid, so return failure.
    if (!isset($xml->short_name)) {
      return;
    }
    $short_name = (string)$xml->short_name;
    $data = [];
    foreach ($xml as $k => $v) {
      $data[$k] = (string)$v;
    }
    $data['releases'] = [];
    if (isset($xml->releases)) {
      foreach ($xml->releases->children() as $release) {
        $version = (string)$release->version;
        $data['releases'][$version] = [];
        foreach ($release->children() as $k => $v) {
          $data['releases'][$version][$k] = (string)$v;
        }
        $data['releases'][$version]['terms'] = [];
        if ($release->terms) {
          foreach ($release->terms->children() as $term) {
            if (!isset($data['releases'][$version]['terms'][(string)$term->name])) {
              $data['releases'][$version]['terms'][(string)$term->name] = [];
            }
            $data['releases'][$version]['terms'][(string)$term->name][] = (string)$term->value;
          }
        }
        if (empty($data['releases'][$version]['version_major'])) {
          // Check for development snapshots


          // Figure out what the currently installed major version is. We need
          // to handle both contribution (e.g. "5.x-1.3", major = 1) and core
          // (e.g. "5.1", major = 5) version strings.
          $matches = [];
          if (preg_match('/^(\d+\.x-)?(\d+)\..*$/', $version, $matches)) {
            $data['releases'][$version]['version_major'] = $matches[2];
            $pathVersion = ltrim($version, $matches[2] . '.');
            $pathVersion = $matches[2] . $pathVersion;
            if (!empty($pathVersion)) {
              if (preg_match('@(dev|HEAD)@', $pathVersion)) {
                //$pathVersion = 'dev';
              }
              $data['releases'][$version]['version_patch'] = $pathVersion;
            }
          }
        }
      }
    }

    return $data;
  }

  /**
   * Function to build current project details.
   *
   * @param $version
   * @return array
   */
  public function currentProject($version) {
    $projects['drupal'] = [
      'name' => 'drupal',
      // Only save attributes from the .info file we care about so we do not
      // bloat our RAM usage needlessly.
      'project_type' => 'core',
      'project_status' => TRUE,
    ];

    $install_type = 'official';

    $info = [];

    if (isset($version)) {
      // Check for development snapshots
      if (preg_match('@(dev|HEAD)@', $version)) {
        $install_type = 'dev';
      }

      // Figure out what the currently installed major version is. We need
      // to handle both contribution (e.g. "5.x-1.3", major = 1) and core
      // (e.g. "5.1", major = 5) version strings.
      $matches = [];
      if (preg_match('/^(\d+\.x-)?(\d+)\..*$/', $version, $matches)) {
        $info['major'] = $matches[2];
      }
      elseif (!isset($info['major'])) {
        // This would only happen for version strings that don't follow the
        // drupal.org convention. We let contribs define "major" in their
        // .info in this case, and only if that's missing would we hit this.
        $info['major'] = -1;
      }
    }
    else {
      // No version info available at all.
      $install_type = 'unknown';
      $info['version'] = E::ts('Unknown');
      $info['major'] = -1;
    }

    // Finally, save the results we care about into the $projects array.
    $projects['drupal']['existing_version'] = $version;
    $projects['drupal']['existing_major'] = $info['major'];
    $projects['drupal']['install_type'] = $install_type;

    return $projects;
  }

  /**
   * Function to calculate drupal project status.
   *
   * @param $project_data
   * @param $available
   */
  public function calculateProjectUpdateStatus(&$project_data, $available) {
    foreach (['title', 'link'] as $attribute) {
      if (!isset($project_data[$attribute]) && isset($available[$attribute])) {
        $project_data[$attribute] = $available[$attribute];
      }
    }

    // If the project status is marked as something bad, there's nothing else
    // to consider.
    if (isset($available['project_status'])) {
      switch ($available['project_status']) {
        case 'insecure':
          $project_data['status'] = self::UPDATE_NOT_SECURE;
          if (empty($project_data['extra'])) {
            $project_data['extra'] = [];
          }
          $project_data['extra'][] = [
            'class' => ['project-not-secure'],
            'label' => E::ts('Project not secure'),
            'data' => E::ts('This project has been labeled insecure by the Drupal security team, and is no longer available for download. Immediately disabling everything included by this project is strongly recommended!'),
          ];
          break;
        case 'unpublished':
        case 'revoked':
          $project_data['status'] = self::UPDATE_REVOKED;
          if (empty($project_data['extra'])) {
            $project_data['extra'] = [];
          }
          $project_data['extra'][] = [
            'class' => ['project-revoked'],
            'label' => E::ts('Project revoked'),
            'data' => E::ts('This project has been revoked, and is no longer available for download. Disabling everything included by this project is strongly recommended!'),
          ];
          break;
        case 'unsupported':
          $project_data['status'] = self::UPDATE_NOT_SUPPORTED;
          if (empty($project_data['extra'])) {
            $project_data['extra'] = [];
          }
          $project_data['extra'][] = [
            'class' => ['project-not-supported'],
            'label' => E::ts('Project not supported'),
            'data' => E::ts('This project is no longer supported, and is no longer available for download. Disabling everything included by this project is strongly recommended!'),
          ];
          break;
        case 'not-fetched':
          $project_data['status'] = self::UPDATE_NOT_FETCHED;
          $project_data['reason'] = E::ts('Failed to get available update data.');
          break;

        default:
          // Assume anything else (e.g. 'published') is valid and we should
          // perform the rest of the logic in this function.
          break;
      }
    }

    if (!empty($project_data['status'])) {
      // We already know the status for this project, so there's nothing else to
      // compute. Record the project status into $project_data and we're done.
      $project_data['project_status'] = $available['project_status'];

      return;
    }

    // Figure out the target major version.
    $existing_major = $project_data['existing_major'];
    $supported_majors = [];
    if (isset($available['supported_majors'])) {
      $supported_majors = explode(',', $available['supported_majors']);
    }
    elseif (isset($available['default_major'])) {
      // Older release history XML file without supported or recommended.
      $supported_majors[] = $available['default_major'];
    }

    if (in_array($existing_major, $supported_majors)) {
      // Still supported, stay at the current major version.
      $target_major = $existing_major;
    }
    elseif (isset($available['recommended_major'])) {
      // Since 'recommended_major' is defined, we know this is the new XML
      // format. Therefore, we know the current release is unsupported since
      // its major version was not in the 'supported_majors' list. We should
      // find the best release from the recommended major version.
      $target_major = $available['recommended_major'];
      $project_data['status'] = self::UPDATE_NOT_SUPPORTED;
    }
    elseif (isset($available['default_major'])) {
      // Older release history XML file without recommended, so recommend
      // the currently defined "default_major" version.
      $target_major = $available['default_major'];
    }
    else {
      // Malformed XML file? Stick with the current version.
      $target_major = $existing_major;
    }

    // Make sure we never tell the admin to downgrade. If we recommended an
    // earlier version than the one they're running, they'd face an
    // impossible data migration problem, since Drupal never supports a DB
    // downgrade path. In the unfortunate case that what they're running is
    // unsupported, and there's nothing newer for them to upgrade to, we
    // can't print out a "Recommended version", but just have to tell them
    // what they have is unsupported and let them figure it out.
    $target_major = max($existing_major, $target_major);

    $release_patch_changed = '';
    $patch = '';

    // If the project is marked as UPDATE_FETCH_PENDING, it means that the
    // data we currently have (if any) is stale, and we've got a task queued
    // up to (re)fetch the data. In that case, we mark it as such, merge in
    // whatever data we have (e.g. project title and link), and move on.
    if (!empty($available['fetch_status']) && $available['fetch_status'] == self::UPDATE_FETCH_PENDING) {
      $project_data['status'] = self::UPDATE_FETCH_PENDING;
      $project_data['reason'] = E::ts('No available update data');
      $project_data['fetch_status'] = $available['fetch_status'];

      return;
    }

    // Defend ourselves from XML history files that contain no releases.
    if (empty($available['releases'])) {
      $project_data['status'] = self::UPDATE_UNKNOWN;
      $project_data['reason'] = E::ts('No available releases found');

      return;
    }
    foreach ($available['releases'] as $version => $release) {
      // First, if this is the existing release, check a few conditions.
      if ($project_data['existing_version'] === $version) {
        if (isset($release['terms']['Release type']) &&
          in_array('Insecure', $release['terms']['Release type'])) {
          $project_data['status'] = self::UPDATE_NOT_SECURE;
        }
        elseif ($release['status'] == 'unpublished') {
          $project_data['status'] = self::UPDATE_REVOKED;
          if (empty($project_data['extra'])) {
            $project_data['extra'] = [];
          }
          $project_data['extra'][] = [
            'class' => ['release-revoked'],
            'label' => E::ts('Release revoked'),
            'data' => E::ts('Your currently installed release has been revoked, and is no longer available for download. Disabling everything included in this release or upgrading is strongly recommended!'),
          ];
        }
        elseif (isset($release['terms']['Release type']) &&
          in_array('Unsupported', $release['terms']['Release type'])) {
          $project_data['status'] = self::UPDATE_NOT_SUPPORTED;
          if (empty($project_data['extra'])) {
            $project_data['extra'] = [];
          }
          $project_data['extra'][] = [
            'class' => ['release-not-supported'],
            'label' => E::ts('Release not supported'),
            'data' => E::ts('Your currently installed release is now unsupported, and is no longer available for download. Disabling everything included in this release or upgrading is strongly recommended!'),
          ];
        }
      }

      // Otherwise, ignore unpublished, insecure, or unsupported releases.
      if ($release['status'] == 'unpublished' ||
        (isset($release['terms']['Release type']) &&
          (in_array('Insecure', $release['terms']['Release type']) ||
            in_array('Unsupported', $release['terms']['Release type'])))) {
        continue;
      }

      // See if this is a higher major version than our target and yet still
      // supported. If so, record it as an "Also available" release.
      // Note: some projects have a HEAD release from CVS days, which could
      // be one of those being compared. They would not have version_major
      // set, so we must call isset first.
      if (isset($release['version_major']) && $release['version_major'] > $target_major) {
        if (in_array($release['version_major'], $supported_majors)) {
          if (!isset($project_data['also'])) {
            $project_data['also'] = [];
          }
          if (!isset($project_data['also'][$release['version_major']])) {
            $project_data['also'][$release['version_major']] = $version;
            $project_data['releases'][$version] = $release;
          }
        }
        // Otherwise, this release can't matter to us, since it's neither
        // from the release series we're currently using nor the recommended
        // release. We don't even care about security updates for this
        // branch, since if a project maintainer puts out a security release
        // at a higher major version and not at the lower major version,
        // they must remove the lower version from the supported major
        // versions at the same time, in which case we won't hit this code.
        continue;
      }

      // Look for the 'latest version' if we haven't found it yet. Latest is
      // defined as the most recent version for the target major version.
      if (!isset($project_data['latest_version']) && isset($release['version_major'])
        && $release['version_major'] == $target_major) {
        $project_data['latest_version'] = $version;
        $project_data['releases'][$version] = $release;
      }

      // Look for the development snapshot release for this branch.
      if (!isset($project_data['dev_version']) && isset($release['version_major'])
        && $release['version_major'] == $target_major
        && isset($release['version_extra'])
        && $release['version_extra'] == 'dev') {
        $project_data['dev_version'] = $version;
        $project_data['releases'][$version] = $release;
      }

      // Look for the 'recommended' version if we haven't found it yet (see
      // phpdoc at the top of this function for the definition).
      if (!isset($project_data['recommended']) && isset($release['version_major'])
        && $release['version_major'] == $target_major
        && isset($release['version_patch'])) {
        if ($patch != $release['version_patch']) {
          $patch = $release['version_patch'];
          $release_patch_changed = $release;
        }
        if (empty($release['version_extra']) && $patch == $release['version_patch']) {
          $project_data['recommended'] = $release_patch_changed['version'];
          $project_data['releases'][$release_patch_changed['version']] = $release_patch_changed;
        }
      }

      // Stop searching once we hit the currently installed version.
      if ($project_data['existing_version'] === $version) {
        break;
      }

      // If we're running a dev snapshot and have a timestamp, stop
      // searching for security updates once we hit an official release
      // older than what we've got. Allow 100 seconds of leeway to handle
      // differences between the datestamp in the .info file and the
      // timestamp of the tarball itself (which are usually off by 1 or 2
      // seconds) so that we don't flag that as a new release.
      if ($project_data['install_type'] == 'dev') {
        if (empty($project_data['datestamp'])) {
          // We don't have current timestamp info, so we can't know.
          continue;
        }
        elseif (isset($release['date']) && ($project_data['datestamp'] + 100 > $release['date'])) {
          // We're newer than this, so we can skip it.
          continue;
        }
      }

      // See if this release is a security update.
      if (isset($release['terms']['Release type'])
        && in_array('Security update', $release['terms']['Release type'])) {
        $project_data['security updates'][] = $release;
      }
    }

    // If we were unable to find a recommended version, then make the latest
    // version the recommended version if possible.
    if (!isset($project_data['recommended']) && isset($project_data['latest_version'])) {
      $project_data['recommended'] = $project_data['latest_version'];
    }

    //
    // Check to see if we need an update or not.
    //

    if (!empty($project_data['security updates'])) {
      // If we found security updates, that always trumps any other status.
      $project_data['status'] = self::UPDATE_NOT_SECURE;
    }

    if (isset($project_data['status'])) {
      // If we already know the status, we're done.
      return;
    }

    // If we don't know what to recommend, there's nothing we can report.
    // Bail out early.
    if (!isset($project_data['recommended'])) {
      $project_data['status'] = self::UPDATE_UNKNOWN;
      $project_data['reason'] = E::ts('No available releases found');

      return;
    }

    // If we're running a dev snapshot, compare the date of the dev snapshot
    // with the latest official version, and record the absolute latest in
    // 'latest_dev' so we can correctly decide if there's a newer release
    // than our current snapshot.
    if ($project_data['install_type'] == 'dev') {
      if (isset($project_data['dev_version']) && $available['releases'][$project_data['dev_version']]['date'] > $available['releases'][$project_data['latest_version']]['date']) {
        $project_data['latest_dev'] = $project_data['dev_version'];
      }
      else {
        $project_data['latest_dev'] = $project_data['latest_version'];
      }
    }

    // Figure out the status, based on what we've seen and the install type.
    switch ($project_data['install_type']) {
      case 'official':
        if ($project_data['existing_version'] === $project_data['recommended'] || $project_data['existing_version'] === $project_data['latest_version']) {
          $project_data['status'] = self::UPDATE_CURRENT;
        }
        else {
          $project_data['status'] = self::UPDATE_NOT_CURRENT;
        }
        break;

      case 'dev':
        $latest = $available['releases'][$project_data['latest_dev']];
        if (empty($project_data['datestamp'])) {
          $project_data['status'] = self::UPDATE_NOT_CHECKED;
          $project_data['reason'] = E::ts('Unknown release date');
        }
        elseif (($project_data['datestamp'] + 100 > $latest['date'])) {
          $project_data['status'] = self::UPDATE_CURRENT;
        }
        else {
          $project_data['status'] = self::UPDATE_NOT_CURRENT;
        }
        break;

      default:
        $project_data['status'] = self::UPDATE_UNKNOWN;
        $project_data['reason'] = E::ts('Invalid info');
    }
  }
}
