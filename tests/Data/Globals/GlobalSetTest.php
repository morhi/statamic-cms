<?php

namespace Tests\Data\Globals;

use Statamic\Facades\Site;
use Statamic\Globals\GlobalSet;
use Tests\TestCase;

class GlobalSetTest extends TestCase
{
    /** @test */
    public function it_gets_file_contents_for_saving_with_a_single_site()
    {
        Site::setConfig([
            'default' => 'en',
            'sites' => [
                'en' => ['name' => 'English', 'locale' => 'en_US', 'url' => 'http://test.com/'],
            ],
        ]);

        $set = (new GlobalSet)->title('The title');

        $variables = $set->makeLocalization('en')->data([
            'array' => ['first one', 'second one'],
            'string' => 'The string',
        ]);

        $set->addLocalization($variables);

        $expected = <<<'EOT'
title: 'The title'
data:
  array:
    - 'first one'
    - 'second one'
  string: 'The string'

EOT;
        $this->assertEquals($expected, $set->fileContents());
    }

    /** @test */
    public function it_gets_file_contents_for_saving_with_multiple_sites()
    {
        Site::setConfig([
            'default' => 'en',
            'sites' => [
                'en' => ['name' => 'English', 'locale' => 'en_US', 'url' => 'http://test.com/'],
                'fr' => ['name' => 'French', 'locale' => 'fr_FR', 'url' => 'http://fr.test.com/'],
                'de' => ['name' => 'German', 'locale' => 'de_DE', 'url' => 'http://test.com/de/'],
            ],
        ]);

        $set = (new GlobalSet)->title('The title');

        $expected = <<<'EOT'
title: 'The title'

EOT;
        $this->assertEquals($expected, $set->fileContents());
    }

    /** @test */
    public function it_contains_added_localizations()
    {
        Site::setConfig([
            'default' => 'en',
            'sites' => [
                'en' => ['name' => 'English', 'locale' => 'en_US', 'url' => 'http://test.com/'],
                'fr' => ['name' => 'French', 'locale' => 'fr_FR', 'url' => 'http://fr.test.com/'],
                'de' => ['name' => 'German', 'locale' => 'de_DE', 'url' => 'http://test.com/de/'],
            ],
        ]);

        $set = (new GlobalSet)->title('The title');

        // Prepare the localizations
        $en = $set->makeLocalization('en')->data([
            'array' => ['first one', 'second one'],
            'string' => 'The string',
        ]);

        $fr = $set->makeLocalization('fr')->data([
            'array' => ['le first one', 'le second one'],
            'string' => 'Le string',
        ]);

        // Add new localizations to the entry
        $set->addLocalization($en);
        $set->addLocalization($fr);

        // Check whether they were set
        $this->assertEquals($en, $set->in('en'));
        $this->assertEquals($en, $set->inDefaultSite());

        $expectedEn = <<<'EOT'
array:
  - 'first one'
  - 'second one'
string: 'The string'

EOT;

        $this->assertEquals($expectedEn, $set->inCurrentSite()->fileContents());

        Site::setCurrent('fr');
        $this->assertEquals($fr, $set->in('fr'));
        $this->assertEquals($fr, $set->inCurrentSite());

        $expectedFr = <<<'EOT'
array:
  - 'le first one'
  - 'le second one'
string: 'Le string'

EOT;
        $this->assertEquals($expectedFr, $set->inCurrentSite()->fileContents());

        // Since we did not set a localizations for the german site the value should be null
        Site::setCurrent('de');
        $this->assertNull($set->inCurrentSite());
    }
}
