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
     * The fetched properties.
     *
     * @var array
     */
    protected $properties = [];

    protected $pagination = [];

    protected $resultCount = 0;

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

        dd($this->properties);

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

    protected function crawl(string $postcode, int $page = 1)
    {
        $client = new Client();
        $crawler = $client->request('GET', self::REQUEST_URL . $postcode . '.html?page=' . $page);

        // Filter script tags
        $scripts = $crawler->filter('script')->each(function ($node) {
            return $node->text();
        });

        // get preloaded data
        $data = json_decode(
            str_replace('window.__PRELOADED_STATE__ = ', '', $scripts[1]),
            true
        );

        // set pagination and result count
        // only need to set result count and pagination for first page crawling
        if ($page === 1) {
            $this->resultCount = $data['results']['resultCount'];
            $this->pagination = $data['pagination'];
        } else {
            // else, just update the current page
            $this->pagination['current'] = $data['pagination']['current'];
        }

        // loop through properties to construct list
        foreach ($data['results']['properties'] as $property) {
            // get display price
            $lastTransaction = $property['transactions'][0];

            // only include the last 10 years
            if (!$this->isDateInYearRange($lastTransaction['dateSold'])) {
                continue;
            }

            $this->properties[] = [
                'address' => $property['address'],
                'type' => $property['propertyType'],
                'displayPrice' => $lastTransaction['displayPrice'],
                'price' => $this->formatPrice($lastTransaction['displayPrice'])
            ];
        }

        // if there are more results, keep crawling
        if ($this->pagination['current'] < $this->pagination['last']) {
            $this->crawl($postcode, $this->pagination['current'] + 1);
        }
    }

    /**
     * Check if date falls within year range.
     *
     * @param string $date
     * @param int $range
     * @return bool
     */
    protected function isDateInYearRange(string $date, int $range = 10): bool
    {
        // calculate difference in years
        $endDate = time();
        $startDate = strtotime($date);
        $diff = floor(($endDate - $startDate) / (365 * 24 * 60 * 60));
        return $diff <= $range;
    }

    /**
     * Format string to fully numeric value.
     *
     * @param string $displayPrice
     * @return int
     */
    protected function formatPrice(string $displayPrice): int
    {
        return (int) filter_var(
            str_replace('&pound;', '', $displayPrice),
            FILTER_SANITIZE_NUMBER_INT
        );
    }
}
