[![Build Status](https://travis-ci.org/grasmash/drupal-security-warning.svg?branch=master)](https://travis-ci.org/grasmash/drupal-security-warning)

This Composer plugin will display a warning when users install Drupal packages that are not supported by [Drupal's Security Team policy](https://www.drupal.org/security-advisory-policy).

The relevant portion of the policy reads:
> Security advisories are only made for issues affecting stable releases (Y.x-Z.0 or higher) in the supported major version branches (at the time of writing Drupal 7.x and Drupal 8.x). That means no security advisories for development releases (-dev), ALPHAs, BETAs or RCs.

Installing or updating a Drupal package with a dev, alpha, beta, or rc release will cause this warning to be displayed.
