@aiprovider @aiprovider_pollinations
Feature: Pollinations AI provider settings
  In order to use the Pollinations AI provider
  As an administrator
  I need to view and configure the provider settings

  Background:
    Given I log in as "admin"

  Scenario: View the Pollinations provider settings page
    When I am on "admin/settings.php?section=aiprovider_pollinations" page
    Then I should see "Pollinations Connection"
    And I should see "Connect to Pollinations"
    And I should see "Rate limiting"
    And I should see "Content safety"
    And I should see "Account & balance"

  Scenario: Enable and configure rate limiting
    When I am on "admin/settings.php?section=aiprovider_pollinations" page
    And I set the field "Set site-wide rate limit" to "1"
    And I set the field "Maximum number of site-wide requests" to "50"
    And I set the field "Set per-user rate limit" to "1"
    And I set the field "Maximum number of requests per user" to "5"
    And I press "Save changes"
    Then I should see "Changes saved"
    And I am on "admin/settings.php?section=aiprovider_pollinations" page
    And the field "Maximum number of site-wide requests" matches value "50"
    And the field "Maximum number of requests per user" matches value "5"

  Scenario: Configure content safety settings
    When I am on "admin/settings.php?section=aiprovider_pollinations" page
    And I set the field "Redact personal information (privacy)" to "1"
    And I set the field "Redact secrets" to "1"
    And I set the field "Block mature content (sexual & violent)" to "1"
    And I press "Save changes"
    Then I should see "Changes saved"

  Scenario: Set a low balance reminder threshold
    When I am on "admin/settings.php?section=aiprovider_pollinations" page
    And I set the field "Low balance reminder threshold" to "250"
    And I press "Save changes"
    Then I should see "Changes saved"
