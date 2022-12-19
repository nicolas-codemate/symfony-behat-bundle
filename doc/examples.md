## Examples
These are fictive scenarios with valid steps from this bundle.
```gherkin
Feature: Examples

  Scenario: Visit a page an check text + markup
    When I visit "/home"
    Then The page shows up
    And I see "hello world"
    And I don't see "goodbye world"
    And I see an a tag "go to hello world"
      | href | /hello-world |
    And I don't see a div tag
      | class | error |

  Scenario: 404 page is styled
    When I visit "/nothere"
    Then the response status code is 404
    And I see "Not Found"
    And I see an img tag
      | src | /notfound.jpg |

  Scenario: JSON Request
    When I make a POST request to "/endpoint.json"
    Then the response status code is 200
    And the response has http headers
      | Content-Type | application/json |
    And the response json matches
    """
    {
      "hello":"world",
      "number": 10
    }
    """
    And the response json contains
    """
    {
      "hello":"world"
    }
    """

  Scenario: Form exists
    When I visit "/home"
    Then The page shows up
    And the page contains a form named "hello"
    And I see an input tag
      | type     | text        |
      | name     | hello[text] |
      | required | required    |

  Scenario: Form can be submitted
    When I visit "/home"
    And I use form "hello"
    And I fill "hi" into "hello[text]"
    And I select "hello[radio]" radio button with value "option1"
    And I check "hello[check]" checkbox
    And I select "option3" from "hello[dropdown]"
    And I select "option1,option2" from "hello[multiselect]"
    And I select "test/fixtures/image.jpg" upload at "hello[file]"
    And I submit the form
    Then the response status code is 302
    When I follow the redirect
    Then the page shows up
    And I See "ok"
    And an e-mail is being sent to "test@noreply" with subject "Test E-Mail"
    And the e-mail contains "Hello World"
    And the e-mail contains "~Hello .* nice to meet you."
    And the e-mail does not contain "Goodbye World"
    And the e-mail is also being sent to "noreply@test"
    And the e-mail has a carbon copy recipient "cc@noreply"
    And the e-mail has a blind carbon copy recipient "bcc@noreply"
    And the e-mail is being sent from "noreply@noreply"
    And the e-mail reply is set to "info@noreply"
  
  Scenario: Submitted form redirects
    When I visit "/home"
    And I use form "hello"
    And I submit the form
    Then I am being redirected to "/success"
    
  Scenario: Command produces log entries
    When I run command "test:produce-logs"
    Then the command has a return value of 0
    And the command outputs "logs were produced"
    And the command does not output "everything ok"
    And the main logfile contains an error entry "no errors"
    And the main logfile contains a warning entry "but warnings"
      | id | 42 |
    And the main logfile doesn't contain an error entry "we have errors"

  Scenario: Command runs silently
    When I run command "test:silence"
    Then the command has a return value of 0
    And the main logfile doesn't contain any error entries
``` 