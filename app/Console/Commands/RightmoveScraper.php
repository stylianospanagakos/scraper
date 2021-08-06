<?php

namespace App\Console\Commands;

use App\Rules\FullPostcode;
use Goutte\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * Class RightmoveScraper
 * @package App\Console\Commands
 *
 * @TODO create Crawling Service class to be consumed by the console command.
 * @TODO Service instance can be generated out of a factory in case different implementations are required in the future.
 */
class RightmoveScraper extends Command
{
    /**
     * @var string
     */
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
     * The progress bar.
     *
     * @var ProgressBar
     */
    protected $progressBar = null;

    /**
     * The fetched properties.
     *
     * @var array
     */
    protected $properties = [];

    /**
     * Top 5 most expensive properties
     *
     * @var array
     */
    protected $topFiveExpensive = [];

    /**
     * The pagination attributes.
     *
     * @var array
     */
    protected $pagination = [];

    /**
     * The total number of properties.
     *
     * @var int
     */
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
    public function handle(): int
    {
        // crawl data
        $this->crawl($this->askPostcode());

        // extract top 5 expensive
        $this->getTop5Expensive();

        // stop progress bar
        if ($this->progressBar) {
            $this->progressBar->finish();
        }

        // format output
        $this->showOutput();

        return 0;
    }

    /**
     * Get postcode entry.
     *
     * @return string
     *
     * @TODO Add ability to specify value for top expensive results in output - now is statically set to 5
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

    /**
     * Crawl through rightmove for specific postcode and page.
     *
     * @param string $postcode
     * @param int $page
     * @return void
     *
     * @TODO add silent error handling to support failed request
     */
    protected function crawl(string $postcode, int $page = 1): void
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

        // only need to set pagination for first page crawling
        if ($page === 1) {
            $this->resultCount = $data['results']['resultCount'];
            $this->pagination = $data['pagination'];
            // start progress bar only for paginated results
            if ($this->pagination['first'] !== $this->pagination['last']) {
                $this->progressBar = $this->output->createProgressBar($this->pagination['last']);
                $this->progressBar->start();
            }
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

            // update properties list
            $this->properties[] = [
                'address' => $property['address'],
                'type' => $property['propertyType'],
                'price' => $this->formatPrice($lastTransaction['displayPrice']),
                'dateSold' => $lastTransaction['dateSold']
            ];
        }

        // advance progress bar if exists
        if ($this->progressBar) {
            $this->progressBar->advance();
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
    public function isDateInYearRange(string $date, int $range = 10): bool
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
    public function formatPrice(string $displayPrice): int
    {
        return (int) filter_var(
            str_replace('&pound;', '', $displayPrice),
            FILTER_SANITIZE_NUMBER_INT
        );
    }

    /**
     * Extract the top 5 most expensive properties
     *
     * @return void
     */
    protected function getTop5Expensive(): void
    {
        $collection = collect($this->properties);
        $this->topFiveExpensive = $collection->sortByDesc('price')
            ->take(5)
            ->map(function ($item) {
                $item['price'] = '£' . number_format($item['price']);
                return $item;
            })
            ->toArray();
    }

    /**
     * Format response.
     *
     * @return void
     */
    protected function showOutput(): void
    {
        $this->newLine(2);
        $this->line('The total number of sold properties for the requested postcode was ' . $this->resultCount . '.');
        $this->newLine();
        if ($this->resultCount > 0) {
            $this->line('Here is a list of the 5 most expensive properties sold in the last 10 years:');
            $this->newLine();
            $this->table(
                ['Address', 'Type', 'Price', 'Date Sold'],
                $this->topFiveExpensive
            );
        }
    }
}
