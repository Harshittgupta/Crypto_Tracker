<?php
/**
 * Helper functions for API calls with caching and fallback mechanisms
 */

/**
 * Fetch cryptocurrency price data with caching and fallback
 * 
 * @param array $coin_ids List of CoinGecko coin IDs
 * @param array $options Additional options (cache_time, vs_currency, include_24h_change, etc.)
 * @return array Price data
 */

 function fetch_crypto_prices($coin_ids, $options = []) {
    // Try the markets endpoint first (more reliable)
    $markets_url = "https://api.coingecko.com/api/v3/coins/markets?vs_currency=usd&ids=" . implode(",", $coin_ids) . "&order=market_cap_desc&sparkline=false";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $markets_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $market_data = json_decode($response, true);
    
    // If we got valid data, reformat it to match the expected structure
    if (is_array($market_data) && !empty($market_data)) {
        $result = [];
        
        foreach ($market_data as $coin) {
            if (isset($coin['id']) && isset($coin['current_price'])) {
                $coin_id = $coin['id'];
                $result[$coin_id] = [
                    'usd' => $coin['current_price']
                ];
                
                // Add 24h change if available
                if (isset($coin['price_change_percentage_24h'])) {
                    $result[$coin_id]['usd_24h_change'] = $coin['price_change_percentage_24h'];
                }
                
                // Add market cap if option enabled and data available
                if (isset($options['include_market_cap']) && 
                    $options['include_market_cap'] && 
                    isset($coin['market_cap'])) {
                    $result[$coin_id]['usd_market_cap'] = $coin['market_cap'];
                }
                
                // Add volume if option enabled and data available
                if (isset($options['include_volume']) && 
                    $options['include_volume'] && 
                    isset($coin['total_volume'])) {
                    $result[$coin_id]['usd_24h_vol'] = $coin['total_volume'];
                }
            }
        }
        
        return $result;
    }
    
    // If that failed, fall back to the original simple/price endpoint
    return fetch_simple_price_fallback($coin_ids, $options);
}
function fetch_simple_price_fallback($coin_ids, $options = []) {
    // Set default options
    $defaults = [
        'cache_time' => 300, // Cache time in seconds (5 minutes)
        'vs_currency' => 'usd',
        'include_24h_change' => true,
        'include_7d_change' => false,
        'include_30d_change' => false,
        'include_market_cap' => false,  // Add default value
        'include_volume' => false,      // Add default value
        'fallback_time' => 86400 // Fallback data validity (24 hours)
    ];
    
    $options = array_merge($defaults, $options);
    
    // Create cache directory if it doesn't exist
    $cache_dir = __DIR__ . '/../cache';
    if (!is_dir($cache_dir)) {
        mkdir($cache_dir, 0755, true);
    }
    
    // Generate cache file name based on parameters
    $cache_key = md5(json_encode($coin_ids) . json_encode($options));
    $cache_file = $cache_dir . '/prices_' . $cache_key . '.json';
    
    // Check if cache exists and is fresh
    $use_cache = false;
    $cache_data = [];
    
    if (file_exists($cache_file)) {
        $cache_data = json_decode(file_get_contents($cache_file), true);
        $cache_time = $cache_data['timestamp'] ?? 0;
        $current_time = time();
        
        if ($current_time - $cache_time < $options['cache_time']) {
            // Cache is fresh, use it
            $use_cache = true;
        }
    }
    
    if ($use_cache) {
        return $cache_data['data'];
    }
    
    // Cache is not available or not fresh, fetch from API
    $api_params = [];
    $api_params[] = 'ids=' . implode(',', $coin_ids);
    $api_params[] = 'vs_currencies=' . $options['vs_currency'];
    
    // Explicitly add the 24h change parameter
    $api_params[] = 'include_24h_change=true';
    
    if ($options['include_7d_change']) $api_params[] = 'include_7d_change=true';
    if ($options['include_30d_change']) $api_params[] = 'include_30d_change=true';
    if ($options['include_market_cap']) $api_params[] = 'include_market_cap=true';
    if ($options['include_volume']) $api_params[] = 'include_24h_vol=true';
    
    $apiUrl = "https://api.coingecko.com/api/v3/simple/price?" . implode('&', $api_params);
    
    // Use a random user agent to avoid being blocked
    $user_agents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.1.1 Safari/605.1.15',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.212 Safari/537.36',
        'CryptoTracker Portfolio App'
    ];
    $user_agent = $user_agents[array_rand($user_agents)];
    
    // Initialize cURL session
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15); // Shorter timeout
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Cache-Control: no-cache'
    ]);
    
    // Execute request
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // If request successful and response is JSON
    if ($http_code == 200 && $response) {
        $price_data = json_decode($response, true);
        
        if (json_last_error() === JSON_ERROR_NONE && is_array($price_data)) {
            // Cache successful response
            file_put_contents($cache_file, json_encode([
                'timestamp' => time(),
                'data' => $price_data
            ]));
            
            return $price_data;
        }
    }
    
    // API request failed, try to use existing cache even if expired
    if (file_exists($cache_file)) {
        $cache_data = json_decode(file_get_contents($cache_file), true);
        $cache_time = $cache_data['timestamp'] ?? 0;
        $current_time = time();
        
        // Use expired cache as fallback if it's not too old
        if ($current_time - $cache_time < $options['fallback_time']) {
            // Log this fallback usage
            error_log("CryptoTracker: Using fallback price data from cache. API request failed.");
            return $cache_data['data'];
        }
    }
    
    // If all fails, return empty array
    error_log("CryptoTracker: Failed to fetch price data and no valid cache available. API URL: $apiUrl, HTTP Code: $http_code");
    return [];
}

/**
 * Fetch historical data for a cryptocurrency
 * 
 * @param string $coin_id CoinGecko coin ID
 * @param int $days Number of days of data to fetch
 * @param string $vs_currency Currency to fetch data in
 * @return array Historical data
 */
function fetch_historical_data($coin_id, $days = 7, $vs_currency = 'usd') {
    // Create cache directory if it doesn't exist
    $cache_dir = __DIR__ . '/../cache';
    if (!is_dir($cache_dir)) {
        mkdir($cache_dir, 0755, true);
    }
    
    // Cache settings
    $cache_time = 3600; // 1 hour for historical data
    $fallback_time = 86400 * 7; // 7 days
    
    // Generate cache file name
    $cache_key = md5($coin_id . $days . $vs_currency);
    $cache_file = $cache_dir . '/history_' . $cache_key . '.json';
    
    // Check if cache exists and is fresh
    $use_cache = false;
    $cache_data = [];
    
    if (file_exists($cache_file)) {
        $cache_data = json_decode(file_get_contents($cache_file), true);
        $cache_time_stored = $cache_data['timestamp'] ?? 0;
        $current_time = time();
        
        if ($current_time - $cache_time_stored < $cache_time) {
            // Cache is fresh, use it
            $use_cache = true;
        }
    }
    
    if ($use_cache) {
        return $cache_data['data'];
    }
    
    // Cache is not available or not fresh, fetch from API
    $apiUrl = "https://api.coingecko.com/api/v3/coins/{$coin_id}/market_chart?vs_currency={$vs_currency}&days={$days}";
    
    // Use a random user agent to avoid being blocked
    $user_agents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.1.1 Safari/605.1.15',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.212 Safari/537.36',
        'CryptoTracker Portfolio App'
    ];
    $user_agent = $user_agents[array_rand($user_agents)];
    
    // Initialize cURL session
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    // Execute request
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // If request successful and response is JSON
    if ($http_code == 200 && $response) {
        $historical_data = json_decode($response, true);
        
        if (json_last_error() === JSON_ERROR_NONE && is_array($historical_data)) {
            // Cache successful response
            file_put_contents($cache_file, json_encode([
                'timestamp' => time(),
                'data' => $historical_data
            ]));
            
            return $historical_data;
        }
    }
    
    // API request failed, try to use existing cache even if expired
    if (file_exists($cache_file)) {
        $cache_data = json_decode(file_get_contents($cache_file), true);
        $cache_time_stored = $cache_data['timestamp'] ?? 0;
        $current_time = time();
        
        // Use expired cache as fallback if it's not too old
        if ($current_time - $cache_time_stored < $fallback_time) {
            error_log("CryptoTracker: Using fallback historical data from cache. API request failed.");
            return $cache_data['data'];
        }
    }
    
    // If all fails, return empty array
    error_log("CryptoTracker: Failed to fetch historical data and no valid cache available. API URL: $apiUrl, HTTP Code: $http_code");
    return ['prices' => [], 'market_caps' => [], 'total_volumes' => []];
}

if (!function_exists('get_coingecko_id')) {
/**
 * Get the CoinGecko ID for a given cryptocurrency symbol
 * 
 * @param string $symbol The cryptocurrency symbol (e.g., BTC)
 * @return string The corresponding CoinGecko ID
 */
function get_coingecko_id($symbol) {
    $symbol = strtolower($symbol);
    
    // Common crypto symbols to their CoinGecko IDs mapping
    $symbol_to_id_map = [
        'btc' => 'bitcoin',
        'eth' => 'ethereum',
        'bnb' => 'binancecoin',
        'ada' => 'cardano',
        'sol' => 'solana',
        'xrp' => 'ripple',
        'dot' => 'polkadot',
        'doge' => 'dogecoin',
        'avax' => 'avalanche-2',
        'link' => 'chainlink',
        'ltc' => 'litecoin',
        'bch' => 'bitcoin-cash',
        'xlm' => 'stellar',
        'uni' => 'uniswap',
        'matic' => 'polygon',
        'etc' => 'ethereum-classic',
        'algo' => 'algorand',
        'atom' => 'cosmos',
        'icp' => 'internet-computer',
        'fil' => 'filecoin',
        'vet' => 'vechain',
        'trx' => 'tron',
        'theta' => 'theta-token',
        'xmr' => 'monero',
        'cake' => 'pancakeswap-token',
        'axs' => 'axie-infinity',
        'shib' => 'shiba-inu',
        'neo' => 'neo',
        'egld' => 'elrond-erd-2',
        'eos' => 'eos',
        'flow' => 'flow',
        'xtz' => 'tezos',
        'ftm' => 'fantom',
        'hbar' => 'hedera-hashgraph',
        'zec' => 'zcash',
        'mana' => 'decentraland',
        'enj' => 'enjincoin',
        'grt' => 'the-graph',
        'chz' => 'chiliz',
        'bat' => 'basic-attention-token',
        'mkr' => 'maker',
        'sand' => 'the-sandbox',
        'waves' => 'waves',
        'dash' => 'dash',
        'comp' => 'compound-governance-token',
        'one' => 'harmony',
        'kcs' => 'kucoin-shares',
        'hot' => 'holotoken',
        'nexo' => 'nexo',
        'qnt' => 'quant-network',
        'cel' => 'celsius-degree-token',
        'rune' => 'thorchain',
        'ar' => 'arweave',
        'snx' => 'synthetix-network-token',
        'zil' => 'zilliqa',
        'sushi' => 'sushi',
        'iota' => 'iota',
        'yfi' => 'yearn-finance',
        'xdc' => 'xdce-crowd-sale',
        'btg' => 'bitcoin-gold',
        'omg' => 'omisego',
        'ksm' => 'kusama',
        'gala' => 'gala',
        'dcr' => 'decred',
        'hnt' => 'helium'
    ];
    
    return isset($symbol_to_id_map[$symbol]) ? $symbol_to_id_map[$symbol] : $symbol;
}
}