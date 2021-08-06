<?php

namespace Tests\Unit;

use App\Console\Commands\RightmoveScraper;
use App\Rules\FullPostcode;
use PHPUnit\Framework\TestCase;

class RightmoveTest extends TestCase
{
    /**
     * Test if postcode regex is valid by providing list of all UK postcode formats.
     *
     * @return void
     */
    public function test_value_entered_is_postcode()
    {
        // list all possible UK postcode formats
        // https://en.wikipedia.org/wiki/Postcodes_in_the_United_Kingdom
        $postcodes = ['AA9A 9AA', 'A9A 9AA', 'A9 9AA', 'A99 9AA', 'AA9 9AA', 'AA99 9AA'];
        $invalid = [1231, 'sdkjasd'];
        $output = true;
        foreach ($postcodes as $postcode) {
            $output = $output && preg_match(FullPostcode::UK_REGEX, $postcode);
        }
        foreach ($invalid as $item) {
            $output = $output && !(bool) preg_match(FullPostcode::UK_REGEX, $item);
        }

        $this->assertTrue($output);
    }
}
