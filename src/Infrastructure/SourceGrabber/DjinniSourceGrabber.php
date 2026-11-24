<?php

declare(strict_types=1);

namespace App\Infrastructure\SourceGrabber;

use App\Domain\Source\Entity\SourceGrabber;
use App\Domain\Source\Grabber\GrabbedCompany;
use App\Domain\Source\Grabber\GrabbedVacancy;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function array_last;
use function explode;
use function max;
use function sleep;

class DjinniSourceGrabber extends AbstractSourceGrabber
{
    public const SourceGrabber GRABBER = SourceGrabber::Djinni;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    private function resolveMaxPage(Crawler $node): int
    {
        $maxPage    = 1;
        $pagination = $node->filter('#jobs_main ul.pagination');

        if ($pagination->count() > 0 && $pagination->filter('a.page-link')->count() > 0) {
            $pages   = $pagination->filter('a.page-link')->each(fn (Crawler $node) => (int) $node->text());
            $maxPage = (int) max($pages);
        }

        return $maxPage ?: 1;
    }

    public function grabPage(string $url, int $page): array
    {
        $url .= "&page=$page";
        $response = $this->httpClient->request('GET', $url);
        $crawler  = new Crawler($response->getContent());

        return $crawler->filter('div.job-item')->each(fn ($node) => $this->parseVacancy($node));
    }

    public function grab(string $url): array
    {
        $response = $this->httpClient->request('GET', $url);
        $crawler  = new Crawler($response->getContent());
        $maxPage  = $this->resolveMaxPage($crawler);
        $result   = [];
        sleep(self::GRAB_DELAY);

        // grab each page
        for ($page = 1; $page <= $maxPage; $page++) {
            $result = [...$result, ...$this->grabPage($url, $page)];
            sleep(self::GRAB_DELAY);
        }

        return $result;
    }

    public function parseCompany(Crawler $node): ?GrabbedCompany
    {
        $positionNode = $node->filter('h2.job-item__position');

        // Step up to the main wrapper, then filter down to the company span
        $company = $positionNode
            ->closest('.col')
            ->filter('span.text-secondary')
            ->getNode(0)?->nodeValue;

        return new GrabbedCompany(
            url: null,
            name: $this->cleanText($company),
        );
    }

    public function parseVacancy(Crawler $node): GrabbedVacancy
    {
        $vacancyUrl  = 'https://djinni.co' . $node->filter('a.job_item__header-link')->attr('href');
        $externalId  = array_last(explode('-', $node->attr('id')));
        $title       = $this->cleanText($node->filter('h2.job-item__position')->getNode(0)?->nodeValue);
        $text        = $node->filter("div[id=job-description-{$externalId}]")->filter('.js-truncated-text')->getNode(0)?->nodeValue;
        $description = $this->cleanText($text);
        $company     = $this->parseCompany($node);

        // grab salary
        $positionNode = $node->filter('h2.job-item__position');
        $salary       = $positionNode
            ->ancestors()->first() // Steps up to <div class="d-flex align-items-baseline...">
            ->filter('span[data-bs-toggle="tooltip"]')
            ->getNode(0)?->nodeValue;

        return new GrabbedVacancy(
            externalId: $externalId,
            url: $vacancyUrl,
            title: $title,
            description: $description,
            salary: $salary,
            company: $company,
        );
    }
}
