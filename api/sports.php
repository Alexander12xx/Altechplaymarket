<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Match.php';

/**
 * Sports API Integration
 * Fetches sports data from external API and creates matches
 */

class SportsAPI {
    private $apiUrl = 'https://v3.football.api-sports.io';
    private $apiKey; // Set your API key here
    private $cacheFile;
    private $cacheDuration = 300; // 5 minutes

    public function __construct() {
        $this->cacheFile = __DIR__ . '/../cache/sports_cache.json';
        $this->apiKey = 'YOUR_API_SPORTS_KEY'; // Replace with actual API key
    }

    /**
     * Fetch live matches
     */
    public function fetchLiveMatches() {
        $cacheData = $this->getCachedData('live_matches');

        if ($cacheData !== null) {
            return $cacheData;
        }

        try {
            $url = $this->apiUrl . '/fixtures?live=all';
            $response = $this->makeAPIRequest($url);

            if (isset($response['response'])) {
                $matches = $this->formatLiveMatches($response['response']);
                $this->setCachedData('live_matches', $matches);
                return $matches;
            }

            return [];

        } catch (Exception $e) {
            error_log("Sports API Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Fetch upcoming matches
     */
    public function fetchUpcomingMatches($leagueId = null) {
        $cacheKey = 'upcoming_matches_' . ($leagueId ?? 'all');
        $cacheData = $this->getCachedData($cacheKey);

        if ($cacheData !== null) {
            return $cacheData;
        }

        try {
            $url = $this->apiUrl . '/fixtures';
            if ($leagueId) {
                $url .= '?league=' . $leagueId;
            }
            $url .= '&next=10'; // Get next 10 matches

            $response = $this->makeAPIRequest($url);

            if (isset($response['response'])) {
                $matches = $this->formatUpcomingMatches($response['response']);
                $this->setCachedData($cacheKey, $matches);
                return $matches;
            }

            return [];

        } catch (Exception $e) {
            error_log("Sports API Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Fetch match odds
     */
    public function fetchMatchOdds($fixtureId) {
        $cacheKey = 'odds_' . $fixtureId;
        $cacheData = $this->getCachedData($cacheKey);

        if ($cacheData !== null) {
            return $cacheData;
        }

        try {
            $url = $this->apiUrl . '/odds?fixture=' . $fixtureId . '&bookmaker=1';
            $response = $this->makeAPIRequest($url);

            if (isset($response['response'])) {
                $odds = $this->formatOdds($response['response'][0]['bookmakers'][0]['bets'] ?? []);
                $this->setCachedData($cacheKey, $odds);
                return $odds;
            }

            return null;

        } catch (Exception $e) {
            error_log("Sports API Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get leagues
     */
    public function getLeagues() {
        $cacheData = $this->getCachedData('leagues');

        if ($cacheData !== null) {
            return $cacheData;
        }

        try {
            $url = $this->apiUrl . '/leagues?current=true';
            $response = $this->makeAPIRequest($url);

            if (isset($response['response'])) {
                $leagues = $this->formatLeagues($response['response']);
                $this->setCachedData('leagues', $leagues);
                return $leagues;
            }

            return [];

        } catch (Exception $e) {
            error_log("Sports API Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Make API request
     */
    private function makeAPIRequest($url) {
        $headers = [
            'x-rapidapi-key: ' . $this->apiKey,
            'x-rapidapi-host: v3.football.api-sports.io'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, API_REQUEST_TIMEOUT);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("API request failed with HTTP code: $httpCode");
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Failed to parse JSON response");
        }

        return $data;
    }

    /**
     * Format live matches for our system
     */
    private function formatLiveMatches($matches) {
        $formatted = [];

        foreach ($matches as $match) {
            if (!isset($match['fixture']) || !isset($match['teams'])) {
                continue;
            }

            $fixture = $match['fixture'];
            $teams = $match['teams'];

            // Calculate basic odds (will be updated with real odds)
            $oddsA = $this->calculateBasicOdds($teams['home']['id'], $teams['away']['id']);
            $oddsB = $this->calculateBasicOdds($teams['away']['id'], $teams['home']['id']);

            $formatted[] = [
                'api_fixture_id' => $fixture['id'],
                'title' => $teams['home']['name'] . ' vs ' . $teams['away']['name'],
                'category' => CATEGORY_E_SPORTS,
                'match_time' => date('Y-m-d H:i:s', $fixture['date']),
                'status' => MATCH_STATUS_LIVE,
                'team_a' => $teams['home']['name'],
                'team_b' => $teams['away']['name'],
                'odds_a' => $oddsA,
                'odds_b' => $oddsB,
                'score' => ($match['goals']['home'] ?? 0) . ' - ' . ($match['goals']['away'] ?? 0),
                'minute' => $fixture['status']['elapsed'] ?? null
            ];
        }

        return $formatted;
    }

    /**
     * Format upcoming matches for our system
     */
    private function formatUpcomingMatches($matches) {
        $formatted = [];

        foreach ($matches as $match) {
            if (!isset($match['fixture']) || !isset($match['teams'])) {
                continue;
            }

            $fixture = $match['fixture'];
            $teams = $match['teams'];

            // Calculate basic odds
            $oddsA = $this->calculateBasicOdds($teams['home']['id'], $teams['away']['id']);
            $oddsB = $this->calculateBasicOdds($teams['away']['id'], $teams['home']['id']);

            $formatted[] = [
                'api_fixture_id' => $fixture['id'],
                'title' => $teams['home']['name'] . ' vs ' . $teams['away']['name'],
                'category' => CATEGORY_E_SPORTS,
                'match_time' => date('Y-m-d H:i:s', $fixture['date']),
                'status' => MATCH_STATUS_UPCOMING,
                'team_a' => $teams['home']['name'],
                'team_b' => $teams['away']['name'],
                'odds_a' => $oddsA,
                'odds_b' => $oddsB,
                'league' => $match['league']['name'] ?? 'Unknown League'
            ];
        }

        return $formatted;
    }

    /**
     * Format odds data
     */
    private function formatOdds($bets) {
        $odds = [];

        foreach ($bets as $bet) {
            if ($bet['name'] === 'Match Winner') {
                foreach ($bet['values'] as $value) {
                    if ($value['value'] === 'Home') {
                        $odds['home'] = (float)$value['odd'];
                    } else if ($value['value'] === 'Away') {
                        $odds['away'] = (float)$value['odd'];
                    }
                }
                break;
            }
        }

        return $odds;
    }

    /**
     * Format leagues
     */
    private function formatLeagues($leagues) {
        $formatted = [];

        foreach ($leagues as $league) {
            $formatted[] = [
                'id' => $league['league']['id'],
                'name' => $league['league']['name'],
                'country' => $league['country']['name'],
                'season' => $league['seasons'][0]['year'] ?? null,
                'logo' => $league['league']['logo'] ?? null
            ];
        }

        return $formatted;
    }

    /**
     * Calculate basic odds (simplified algorithm)
     */
    private function calculateBasicOdds($team1Id, $team2Id) {
        // This is a simplified calculation
        // In production, you'd use team rankings, historical data, etc.
        $baseOdds = 1.5 + (mt_rand() / mt_getrandmax()) * 2.0;
        return round($baseOdds, 2);
    }

    /**
     * Get cached data
     */
    private function getCachedData($key) {
        if (!file_exists($this->cacheFile)) {
            return null;
        }

        $cache = json_decode(file_get_contents($this->cacheFile), true);
        if (!$cache || !isset($cache[$key])) {
            return null;
        }

        $cacheEntry = $cache[$key];
        if (time() - $cacheEntry['timestamp'] > $this->cacheDuration) {
            return null;
        }

        return $cacheEntry['data'];
    }

    /**
     * Set cached data
     */
    private function setCachedData($key, $data) {
        $cache = [];
        if (file_exists($this->cacheFile)) {
            $cache = json_decode(file_get_contents($this->cacheFile), true) ?: [];
        }

        $cache[$key] = [
            'data' => $data,
            'timestamp' => time()
        ];

        $cacheDir = dirname($this->cacheFile);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        file_put_contents($this->cacheFile, json_encode($cache));
    }

    /**
     * Sync matches to database
     */
    public function syncMatchesToDatabase() {
        $match = new Match();
        $upcomingMatches = $this->fetchUpcomingMatches();

        foreach ($upcomingMatches as $matchData) {
            // Check if match already exists
            $existing = $match->getMatchByApiId($matchData['api_fixture_id']);

            if (!$existing) {
                // Create new match
                $match->createMatch([
                    'title' => $matchData['title'],
                    'category' => $matchData['category'],
                    'match_time' => $matchData['match_time'],
                    'status' => $matchData['status'],
                    'team_a' => $matchData['team_a'],
                    'team_b' => $matchData['team_b'],
                    'odds_a' => $matchData['odds_a'],
                    'odds_b' => $matchData['odds_b'],
                    'created_by' => null // System created
                ]);
            } else {
                // Update existing match odds
                $match->updateOdds($existing['id'], $matchData['odds_a'], $matchData['odds_b']);
            }
        }
    }
}

// Example usage for testing
if (isset($_GET['action'])) {
    $sportsAPI = new SportsAPI();

    switch ($_GET['action']) {
        case 'live':
            jsonResponse($sportsAPI->fetchLiveMatches());
            break;

        case 'upcoming':
            $leagueId = $_GET['league'] ?? null;
            jsonResponse($sportsAPI->fetchUpcomingMatches($leagueId));
            break;

        case 'leagues':
            jsonResponse($sportsAPI->getLeagues());
            break;

        case 'odds':
            $fixtureId = $_GET['fixture'] ?? 0;
            jsonResponse($sportsAPI->fetchMatchOdds($fixtureId));
            break;

        case 'sync':
            $sportsAPI->syncMatchesToDatabase();
            jsonResponse(['message' => 'Matches synced successfully']);
            break;

        default:
            jsonError('Invalid action');
    }
}
?>