# Advanced Search Capabilities - Architecture Design

## Overview
This document outlines the design for implementing advanced search capabilities in the ArtisanPack UI CMS Framework, including full-text search, search indexing, API endpoints, result ranking, faceted search, and search analytics.

## Current Content Structure Analysis
Based on the existing models, the searchable content includes:

### Content Model
- **Primary searchable fields**: `title`, `slug`, `content` (body text)
- **Filterable fields**: `type`, `status`, `author_id`, `published_at`
- **Meta fields**: JSON column with flexible key-value data
- **Relationships**: author (User), parent/children (Content), terms (Term)

### Term Model
- **Fields**: `name`, `slug`, `taxonomy_id`, `parent_id`
- **Relationships**: taxonomy (Taxonomy), parent/children (Term), content (many-to-many)

### Taxonomy Model
- Used for organizing terms into categories, tags, etc.

## Search Strategy Decision: Database-Based Full-Text Search

**Chosen Approach**: MySQL/PostgreSQL full-text search with custom indexing
**Rationale**:
- Simpler deployment (no external dependencies like Elasticsearch)
- Good performance for small to medium datasets
- Laravel-native implementation
- Easier maintenance and backup
- Can be upgraded to external search engine later if needed

## Architecture Components

### 1. Search Index System

#### Search Index Model
```php
class SearchIndex extends Model
{
    protected $fillable = [
        'searchable_type',    // Content, Term, etc.
        'searchable_id',      // ID of the searchable entity
        'title',              // Indexed title
        'content',            // Full-text searchable content
        'excerpt',            // Short description/excerpt
        'keywords',           // Comma-separated keywords
        'type',               // content type, taxonomy name, etc.
        'status',             // published, draft, etc.
        'author_id',          // Content author
        'published_at',       // Publication date
        'relevance_boost',    // Manual relevance multiplier
        'meta_data',          // JSON: additional searchable metadata
    ];

    protected $casts = [
        'meta_data' => 'array',
        'published_at' => 'datetime',
        'relevance_boost' => 'decimal:2',
    ];
}
```

#### Database Migration
```sql
CREATE TABLE search_indices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    searchable_type VARCHAR(255) NOT NULL,
    searchable_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(500) NOT NULL,
    content TEXT,
    excerpt VARCHAR(500),
    keywords TEXT,
    type VARCHAR(100),
    status VARCHAR(50),
    author_id BIGINT UNSIGNED,
    published_at TIMESTAMP NULL,
    relevance_boost DECIMAL(3,2) DEFAULT 1.00,
    meta_data JSON,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    INDEX idx_searchable (searchable_type, searchable_id),
    INDEX idx_type_status (type, status),
    INDEX idx_author (author_id),
    INDEX idx_published (published_at),
    FULLTEXT(title, content, excerpt, keywords) -- MySQL full-text index
);
```

### 2. Search Service Architecture

#### SearchService Class
```php
class SearchService
{
    public function search(SearchRequest $request): SearchResult
    public function indexContent(Model $model): void
    public function removeFromIndex(Model $model): void
    public function reindexAll(): void
    public function getSearchSuggestions(string $query): array
    public function getFacets(SearchRequest $request): array
    public function logSearch(string $query, array $filters, int $resultCount): void
}
```

### 3. API Endpoint Structure

#### Search Endpoints
```
GET /api/cms/search
- Main search endpoint with full-text search
- Query parameters: q, type, status, author, date_from, date_to, limit, offset, sort

GET /api/cms/search/facets
- Returns available facets for filtering
- Query parameters: q (optional pre-filter)

GET /api/cms/search/suggestions
- Auto-complete suggestions based on partial query
- Query parameters: q, limit

GET /api/cms/search/analytics
- Search analytics dashboard (admin only)
- Query parameters: date_from, date_to, top_queries, failed_queries
```

### 4. Search Result Ranking Algorithm

#### Scoring Components
1. **Text Relevance** (40%): MySQL MATCH() AGAINST() score
2. **Content Type Weight** (20%): Configurable weights per content type
3. **Freshness Score** (15%): Boost recent content
4. **Author Authority** (10%): Boost content from high-authority authors
5. **Manual Boost** (10%): Manual relevance_boost field
6. **Engagement Score** (5%): Based on views, comments, etc. (future)

#### Ranking Formula
```php
final_score = (
    text_relevance * 0.4 +
    type_weight * 0.2 +
    freshness_score * 0.15 +
    author_authority * 0.1 +
    manual_boost * 0.1 +
    engagement_score * 0.05
) * relevance_boost
```

### 5. Faceted Search Implementation

#### Available Facets
- **Content Type**: post, page, video, etc.
- **Status**: published, draft, archived
- **Author**: Content authors
- **Date Range**: Published date ranges
- **Terms**: Categories, tags, custom taxonomies
- **Custom Meta**: Based on meta_data fields

### 6. Search Analytics System

#### SearchAnalytics Model
```php
class SearchAnalytics extends Model
{
    protected $fillable = [
        'query',              // Search query
        'filters',            // Applied filters (JSON)
        'result_count',       // Number of results
        'click_through_rate', // CTR if tracking clicks
        'user_id',            // User who searched (optional)
        'ip_address',         // IP address (hashed for privacy)
        'user_agent',         // User agent
        'execution_time_ms',  // Query execution time
    ];
}
```

## Implementation Plan

### Phase 1: Core Search Infrastructure
1. Create SearchIndex model and migration
2. Implement SearchService with basic indexing
3. Add content observers for automatic indexing
4. Create search command for manual reindexing

### Phase 2: API Endpoints
1. Create SearchController with main search endpoint
2. Implement faceted search endpoint
3. Add search suggestions endpoint
4. Implement proper rate limiting and caching

### Phase 3: Advanced Features
1. Implement search result ranking
2. Add search analytics tracking
3. Create analytics dashboard endpoint
4. Add search management commands

### Phase 4: Testing and Documentation
1. Comprehensive unit and feature tests
2. Performance testing and optimization
3. API documentation
4. Usage examples and guides

## Configuration Options

### Search Configuration
```php
// config/cms.php
'search' => [
    'enabled' => env('CMS_SEARCH_ENABLED', true),
    'index_batch_size' => env('CMS_SEARCH_INDEX_BATCH_SIZE', 100),
    'max_results' => env('CMS_SEARCH_MAX_RESULTS', 1000),
    'cache_ttl' => env('CMS_SEARCH_CACHE_TTL', 3600), // 1 hour
    'analytics_enabled' => env('CMS_SEARCH_ANALYTICS_ENABLED', true),
    'type_weights' => [
        'page' => 1.2,
        'post' => 1.0,
        'video' => 0.9,
        'media' => 0.7,
    ],
    'freshness_decay_days' => env('CMS_SEARCH_FRESHNESS_DECAY', 365),
],
```

## Performance Considerations

1. **Indexing Strategy**: Incremental indexing on content changes
2. **Caching**: Cache search results for popular queries
3. **Database Optimization**: Proper indexing and query optimization
4. **Rate Limiting**: Prevent search abuse
5. **Pagination**: Efficient offset/cursor-based pagination

## Security Considerations

1. **Input Sanitization**: Prevent SQL injection in search queries
2. **Access Control**: Respect content permissions in search results
3. **Rate Limiting**: Prevent search-based DoS attacks
4. **Privacy**: Hash IP addresses in analytics
5. **Content Filtering**: Filter by user permissions and content status

## Future Enhancements

1. **Elasticsearch Integration**: For large-scale deployments
2. **Machine Learning**: Personalized search results
3. **Geographic Search**: Location-based content filtering
4. **Voice Search**: Integration with speech-to-text APIs
5. **Search API Versioning**: For backward compatibility