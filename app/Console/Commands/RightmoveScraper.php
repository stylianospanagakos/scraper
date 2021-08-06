<?php

namespace App\Console\Commands;

use App\Rules\FullPostcode;
use Goutte\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class RightmoveScraper extends Command
{
    const REQUEST_URL = 'https://www.rightmove.co.uk/house-prices/';
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rightmove:crawl';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crawl rightmove.co.uk to fetch property data for the last 10 years';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // get postcode
        $postcode = $this->askPostcode();
        // crawl data
        $this->crawl($postcode);

        return 0;
    }

    /**
     * Get postcode entry.
     *
     * @return string
     */
    protected function askPostcode(): string
    {
        $postcode = $this->ask('Enter the postcode you want to fetch information for');

        if ($error = $this->hasPostcodeError($postcode)) {
            $this->error($error);
            $this->askPostcode();
        }

        return $postcode;
    }

    /**
     * Validate postcode.
     *
     * @param string $value
     * @return string|null
     */
    protected function hasPostcodeError(string $value)
    {
        $validator = Validator::make([
            'postcode' => $value
        ], [
            'postcode' => new FullPostcode()
        ]);

        return $validator->fails()
            ? $validator->errors()->first('postcode')
            : null;
    }

    protected function crawl($postcode)
    {
        $client = new Client();
        $crawler = $client->request('GET', self::REQUEST_URL . $postcode . '.html');

        // Filter script tags
        $scripts = $crawler->filter('script')->each(function ($node) {
            return $node->text();
        });

        // get preloaded data
        $data = json_decode(
            str_replace('window.__PRELOADED_STATE__ = ', '', $scripts[1]),
            true
        );

        // destructure data
        ['resultCount' => $resultCount, 'properties' => $properties] = $data['results'];
        ['pagination' => $pagination] = $data;

        dd($resultCount, $properties, $pagination);
    }
}
