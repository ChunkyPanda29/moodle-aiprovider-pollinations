@aiprovider @aiprovider_pollinations
Feature: Pollinations AI provider settings
  In order to use the Pollinations AI provider
  As an administrator
  I need to view and configure the provider settings

  Background:
    Given I log in as "admin"

  Scenario: View the Pollinations provider settings page
    When I navigate to "Plugins > AI providers > Pollinations AI provider" in site administration
    Then I should see "Pollinations Connection"
    And I should see "Rate limiting"
    And I should see "Content safety"

  Scenario: Enable and configure rate limiting
    When I navigate to "Plugins > AI providers > Pollinations AI provider" in site administration
    And I set the field "Set site-wide rate limit" to "1"
    And I set the field "Maximum number of site-wide requests" to "50"
    And I set the field "Set per-user rate limit" to "1"
    And I set the field "Maximum number of requests per user" to "5"
    And I press "Save changes"
    Then I should see "Changes saved"

  Scenario: Configure content safety settings
    When I navigate to "Plugins > AI providers > Pollinations AI provider" in site administration
    And I set the field "Redact personal information (privacy)" to "1"
    And I set the field "Redact secrets" to "1"
    And I press "Save changes"
    Then I should see "Changes saved"

  Scenario: Set a low balance reminder threshold
    When I navigate to "Plugins > AI providers > Pollinations AI provider" in site administration
    And I set the field "Low balance reminder threshold" to "250"
    And I press "Save changes"
    Then I should see "Changes saved"
