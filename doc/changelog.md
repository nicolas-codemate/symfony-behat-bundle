# Changelog

## v1.3.0
### Added support for multiselect
`<select multiple>` form fields can now be selected with
```gherkin
And I select "option1,option2" from "hello[multiselect]"
```

### Added check for HTTP headers 
Check the HTTP for headers, including regex compare with `~` at the beginning
```gherkin
Then the response has http headers
  | location | ~/oauth2/authorize |
  | Pragma | no-cache |
```

### Consider HTTP/303 and 308 as valid redirect, too.
This now passes, too if the response code is 303 or 308  
```gherkin
Then I am being redirected to "/test"
```

### Allow regex in some more assertions
```gherkin
Then I am being redirected to "~/test"
Then I see an a tag "~Hello.*"
Then the form contains an input field "~test\[.*_name\]"
Then the command outputs "~Hello"
```

### Negation of command output
```gherkin
Then the command does not output "~Hello"
```

### AbstractDatabaseContext reloaded
The new Abstraction should bring everything you need to create custom contexts based on entities.
You can fully focus upon the business language and let the abstraction do the work for you.
```php
/**
 * @implements AbstractDatabaseContext<Entry>
 */
class MyContext extends AbstractDatabaseContext
{
    #[BeforeScenario]
    public function reset(): void
    {
        // Delete all entries and reset the auto_increment/sequence to 0
        $this->resetDatabase();
        $this->resetSequence();
        // Also reset other (m:n relation) tables
        $this->resetDatabase('another_entity');
    }

    #[Given('there is an entry')]
    public function thereIsAnEntry(TableNode $table): void
    {
        $this->createObject($table);
    }

    #[Given(':id1 is related to :id2')]
    public function isRelatedTo(int $id1, int $id2): void
    {
        $this->createRelation($id1, AnotherEntity::class, $id2, 'relatedTo');
    }

    #[Then('there exists an entry')]
    public function thereExistsAnEntry(TableNode $table): void
    {
        $this->assertObject($table);
    }

    #[Then('there exists no entry')]
    public function thereExistsNoEntry(TableNode $table): void
    {
        $this->assertNoObject($table);
    }
    
    #[Then(':id1 is now related to :id2')]
    public function isNowRelatedTo(int $id1, int $id2): void
    {
        $this->assertCollectionContains($id1, AnotherEntity::class, $id2, 'relatedTo');
    }

    protected function getClassName(): string
    {
        return MyEntity::class;
    }
```
