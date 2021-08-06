<?php

namespace Tests\Unit;

use App\Console\Commands\RightmoveScraper;
use App\Rules\FullPostcode;
use PHPUnit\Framework\TestCase;

class RightmoveTest extends TestCase
{
    /**
     * A basic unit test example.
     *
     * @return void
     */
    public function test_postcode_entered_is_valid()
    {
        // list all possible UK postcode formats
        // https://en.wikipedia.org/wiki/Postcodes_in_the_United_Kingdom
        $postcodes = ['AA9A 9AA', 'A9A 9AA', 'A9 9AA', 'A99 9AA', 'AA9 9AA', 'AA99 9AA'];
        $output = true;
        foreach ($postcodes as $postcode) {
            $output = $output && preg_match(FullPostcode::UK_REGEX, $postcode);
        }
        $this->assertTrue($output);
    }

    public function test_postcode_entered_is_invalid()
    {
        $this->assertFalse((bool) preg_match(FullPostcode::UK_REGEX, 'asdad'));
    }
}
