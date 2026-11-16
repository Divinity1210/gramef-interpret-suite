<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Agent;

class AgentLanguageHelper
{
    /**
     * Cache duration in seconds (10 minutes)
     */
    const CACHE_DURATION = 600;

    /**
     * Get agent languages with caching
     * 
     * @param int $agentId
     * @return array
     */
    public static function getAgentLanguages($agentId)
    {
        $cacheKey = "agent_languages_{$agentId}";
        
        return Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($agentId) {
            return DB::table('agent_language')
                ->where('agent_id', $agentId)
                ->pluck('language_id')
                ->toArray();
        });
    }

    /**
     * Check if agent has specific language
     * 
     * @param int $agentId
     * @param int $languageId
     * @return bool
     */
    public static function hasLanguage($agentId, $languageId)
    {
        $languages = self::getAgentLanguages($agentId);
        return in_array($languageId, $languages);
    }

    /**
     * Check if agent has both languages (for translator jobs)
     * 
     * @param int $agentId
     * @param int $fromLanguageId
     * @param int $toLanguageId
     * @return bool
     */
    public static function hasBothLanguages($agentId, $fromLanguageId, $toLanguageId)
    {
        $languages = self::getAgentLanguages($agentId);
        return in_array($fromLanguageId, $languages) && in_array($toLanguageId, $languages);
    }

    /**
     * Get all agents with specific language (cached)
     * 
     * @param int $languageId
     * @return array
     */
    public static function getAgentsWithLanguage($languageId)
    {
        $cacheKey = "language_agents_{$languageId}";
        
        return Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($languageId) {
            return DB::table('agent_language')
                ->where('language_id', $languageId)
                ->pluck('agent_id')
                ->toArray();
        });
    }

    /**
     * Clear agent language cache
     * 
     * @param int $agentId
     */
    public static function clearAgentLanguageCache($agentId)
    {
        Cache::forget("agent_languages_{$agentId}");
        
        // Also clear language-specific caches that might include this agent
        $languages = DB::table('agent_language')
            ->where('agent_id', $agentId)
            ->pluck('language_id');
            
        foreach ($languages as $languageId) {
            Cache::forget("language_agents_{$languageId}");
        }
    }

    /**
     * Clear all language-related caches
     */
    public static function clearAllLanguageCaches()
    {
        // This is a more aggressive approach - clear all agent language caches
        $agentIds = DB::table('agent_language')->distinct()->pluck('agent_id');
        $languageIds = DB::table('agent_language')->distinct()->pluck('language_id');
        
        foreach ($agentIds as $agentId) {
            Cache::forget("agent_languages_{$agentId}");
        }
        
        foreach ($languageIds as $languageId) {
            Cache::forget("language_agents_{$languageId}");
        }
    }
}