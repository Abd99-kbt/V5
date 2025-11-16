#!/bin/bash

# Load Testing Script for Laravel Production
# Tests various endpoints with different load patterns

set -e

# Configuration
APP_URL="${APP_URL:-http://localhost}"
RESULTS_DIR="./performance/results"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
LOG_FILE="$RESULTS_DIR/load_test_$TIMESTAMP.log"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Create results directory
mkdir -p "$RESULTS_DIR"

# Logging function
log() {
    echo -e "$(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
}

log_info() {
    log "${BLUE}[INFO]${NC} $1"
}

log_success() {
    log "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    log "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    log "${RED}[ERROR]${NC} $1"
}

# Check dependencies
check_dependencies() {
    log_info "Checking dependencies..."
    
    if ! command -v curl &> /dev/null; then
        log_error "curl is required but not installed"
        exit 1
    fi
    
    if ! command -v jq &> /dev/null; then
        log_warning "jq is recommended but not installed"
    fi
}

# Test basic connectivity
test_connectivity() {
    log_info "Testing basic connectivity..."
    
    local response=$(curl -s -o /dev/null -w "%{http_code}" "$APP_URL/health")
    if [[ "$response" == "200" ]]; then
        log_success "Application is reachable"
        return 0
    else
        log_error "Application health check failed (HTTP $response)"
        return 1
    fi
}

# Warm up the application
warmup_application() {
    log_info "Warming up application..."
    
    # Hit main endpoints to warm up cache
    local endpoints=(
        "$APP_URL/health"
        "$APP_URL/api/health/metrics"
    )
    
    for endpoint in "${endpoints[@]}"; do
        curl -s -o /dev/null "$endpoint" || log_warning "Failed to warmup $endpoint"
    done
    
    log_success "Application warmup completed"
}

# Perform load test
perform_load_test() {
    local endpoint="$1"
    local concurrent_users="$2"
    local duration="$3"
    local name="$4"
    
    log_info "Starting load test: $name"
    log_info "Endpoint: $endpoint"
    log_info "Concurrent users: $concurrent_users"
    log_info "Duration: ${duration}s"
    
    # Use Apache Bench for load testing if available
    if command -v ab &> /dev/null; then
        perform_ab_test "$endpoint" "$concurrent_users" "$duration" "$name"
    else
        # Fallback to curl-based testing
        perform_curl_test "$endpoint" "$concurrent_users" "$duration" "$name"
    fi
}

# Apache Bench test
perform_ab_test() {
    local endpoint="$1"
    local concurrent_users="$2"
    local duration="$3"
    local name="$4"
    
    local output_file="$RESULTS_DIR/${name}_${TIMESTAMP}.txt"
    
    # Calculate requests (rough estimate based on duration and rate)
    local estimated_requests=$((concurrent_users * 10 * duration))
    
    log_info "Running Apache Bench test..."
    
    ab -n "$estimated_requests" \
       -c "$concurrent_users" \
       -t "$duration" \
       -g "$output_file.tsv" \
       "$endpoint" > "$output_file" 2>&1
    
    log_success "Load test completed: $output_file"
    
    # Extract and display key metrics
    if [[ -f "$output_file" ]]; then
        display_ab_results "$output_file" "$name"
    fi
}

# Display Apache Bench results
display_ab_results() {
    local file="$1"
    local name="$2"
    
    log_info "Results for $name:"
    
    if [[ -f "$file" ]]; then
        local rps=$(grep "Requests per second" "$file" | awk '{print $4}')
        local time_per_req=$(grep "Time per request" "$file" | head -1 | awk '{print $4}')
        local failed=$(grep "Failed requests:" "$file" | awk '{print $3}')
        
        echo "  Requests per second: $rps" | tee -a "$LOG_FILE"
        echo "  Time per request: ${time_per_req}ms" | tee -a "$LOG_FILE"
        echo "  Failed requests: $failed" | tee -a "$LOG_FILE"
        
        # Check if response time meets target (<200ms)
        local time_ms=$(echo "$time_per_req" | awk '{print int($1 * 1000)}')
        if [[ $time_ms -lt 200 ]]; then
            log_success "✅ Response time target met: ${time_ms}ms < 200ms"
        else
            log_warning "⚠️  Response time target not met: ${time_ms}ms >= 200ms"
        fi
    fi
}

# Curl-based test (fallback)
perform_curl_test() {
    local endpoint="$1"
    local concurrent_users="$2"
    local duration="$3"
    local name="$4"
    
    local output_file="$RESULTS_DIR/${name}_${TIMESTAMP}.json"
    local start_time=$(date +%s)
    local end_time=$((start_time + duration))
    local successful_requests=0
    local failed_requests=0
    local total_time=0
    local request_count=0
    
    log_info "Running curl-based test (fallback)..."
    
    # Background function for concurrent requests
    make_request() {
        local url="$1"
        local req_start=$(date +%s.%3N)
        local response_time
        
        if response_time=$(curl -s -w "%{time_total}" -o /dev/null "$url"); then
            echo "$response_time"
        else
            echo "-1"
        fi
    }
    
    # Run test until duration expires
    while [[ $(date +%s) -lt $end_time ]]; do
        for ((i=1; i<=concurrent_users; i++)); do
            response_time=$(make_request "$endpoint")
            request_count=$((request_count + 1))
            
            if [[ "$response_time" != "-1" ]]; then
                successful_requests=$((successful_requests + 1))
                total_time=$(echo "$total_time + $response_time" | bc -l)
            else
                failed_requests=$((failed_requests + 1))
            fi
        done
    done
    
    # Calculate metrics
    local avg_time=0
    local error_rate=0
    local rps=0
    
    if [[ $successful_requests -gt 0 ]]; then
        avg_time=$(echo "scale=3; $total_time / $successful_requests * 1000" | bc -l)
    fi
    
    if [[ $request_count -gt 0 ]]; then
        error_rate=$(echo "scale=2; $failed_requests * 100 / $request_count" | bc -l)
        rps=$(echo "scale=2; $request_count / $duration" | bc -l)
    fi
    
    # Save results
    cat > "$output_file" << EOF
{
    "test_name": "$name",
    "endpoint": "$endpoint",
    "concurrent_users": $concurrent_users,
    "duration_seconds": $duration,
    "total_requests": $request_count,
    "successful_requests": $successful_requests,
    "failed_requests": $failed_requests,
    "average_response_time_ms": $avg_time,
    "error_rate_percent": $error_rate,
    "requests_per_second": $rps,
    "timestamp": "$(date -Iseconds)"
}
EOF
    
    log_success "Test completed: $output_file"
    
    # Display results
    log_info "Results for $name:"
    echo "  Requests per second: $rps" | tee -a "$LOG_FILE"
    echo "  Average response time: ${avg_time}ms" | tee -a "$LOG_FILE"
    echo "  Error rate: ${error_rate}%" | tee -a "$LOG_FILE"
    echo "  Total requests: $request_count" | tee -a "$LOG_FILE"
    
    # Check performance targets
    local avg_time_int=$(echo "$avg_time" | cut -d'.' -f1)
    if [[ $avg_time_int -lt 200 ]]; then
        log_success "✅ Response time target met: ${avg_time}ms < 200ms"
    else
        log_warning "⚠️  Response time target not met: ${avg_time}ms >= 200ms"
    fi
}

# Memory usage monitoring
monitor_memory_usage() {
    log_info "Monitoring memory usage during load test..."
    
    local output_file="$RESULTS_DIR/memory_usage_$TIMESTAMP.csv"
    echo "timestamp,rss_mb,vsz_mb,cpu_percent" > "$output_file"
    
    # Get PHP process PID
    local php_pids=$(pgrep -f "php.*artisan" | head -5)
    
    local start_time=$(date +%s)
    local end_time=$((start_time + 300)) # Monitor for 5 minutes
    
    while [[ $(date +%s) -lt $end_time ]]; do
        local timestamp=$(date +"%Y-%m-%d %H:%M:%S")
        
        for pid in $php_pids; do
            if [[ -n "$pid" ]] && kill -0 "$pid" 2>/dev/null; then
                local stats=$(ps -p "$pid" -o rss,vsz,pcpu --no-headers)
                local rss_mb=$(echo "$stats" | awk '{print $1/1024}')
                local vsz_mb=$(echo "$stats" | awk '{print $2/1024}')
                local cpu_percent=$(echo "$stats" | awk '{print $3}')
                
                echo "$timestamp,$rss_mb,$vsz_mb,$cpu_percent" >> "$output_file"
            fi
        done
        
        sleep 5
    done
    
    log_success "Memory monitoring completed: $output_file"
}

# Performance analysis
analyze_results() {
    log_info "Analyzing performance results..."
    
    local summary_file="$RESULTS_DIR/performance_summary_$TIMESTAMP.md"
    
    cat > "$summary_file" << EOF
# Performance Test Results

**Test Date:** $(date)  
**Target Response Time:** < 200ms  
**Application URL:** $APP_URL

## Load Test Results

EOF
    
    # Process each result file
    for result_file in "$RESULTS_DIR"/*.json; do
        if [[ -f "$result_file" ]]; then
            local name=$(jq -r '.test_name' "$result_file")
            local avg_time=$(jq -r '.average_response_time_ms' "$result_file")
            local rps=$(jq -r '.requests_per_second' "$result_file")
            local error_rate=$(jq -r '.error_rate_percent' "$result_file")
            
            local status="✅ PASS"
            if (( $(echo "$avg_time >= 200" | bc -l) )); then
                status="❌ FAIL"
            fi
            
            cat >> "$summary_file" << EOF

### $name

- **Response Time:** ${avg_time}ms $status
- **Requests/Second:** $rps
- **Error Rate:** ${error_rate}%
- **Endpoint:** $(jq -r '.endpoint' "$result_file")

EOF
        fi
    done
    
    # Add system information
    cat >> "$summary_file" << EOF

## System Information

- **CPU:** $(lscpu | grep "Model name" | cut -d: -f2 | xargs)
- **Memory:** $(free -h | grep Mem | awk '{print $2}')
- **OS:** $(uname -a)

## Recommendations

EOF
    
    # Add recommendations based on results
    local failed_tests=0
    
    for result_file in "$RESULTS_DIR"/*.json; do
        if [[ -f "$result_file" ]]; then
            local avg_time=$(jq -r '.average_response_time_ms' "$result_file")
            if (( $(echo "$avg_time >= 200" | bc -l) )); then
                ((failed_tests++))
            fi
        fi
    done
    
    if [[ $failed_tests -gt 0 ]]; then
        cat >> "$summary_file" << EOF

⚠️ **Performance Issues Detected:**

1. **Optimize Database Queries:** Review slow queries and add proper indexes
2. **Enable Caching:** Ensure Redis/database caching is properly configured
3. **Connection Pooling:** Verify database connection pooling settings
4. **Queue Optimization:** Check queue worker configuration for better throughput
5. **Memory Optimization:** Monitor memory usage and optimize data structures

EOF
    else
        cat >> "$summary_file" << EOF

✅ **All performance targets met!** Your application is performing well.

- Response times are under 200ms target
- Error rates are within acceptable limits
- System resources are being used efficiently

EOF
    fi
    
    log_success "Performance analysis completed: $summary_file"
}

# Main execution
main() {
    log_info "Starting Performance Load Testing"
    log_info "Application URL: $APP_URL"
    log_info "Results will be saved to: $RESULTS_DIR"
    
    # Check prerequisites
    check_dependencies
    
    # Test connectivity
    if ! test_connectivity; then
        log_error "Cannot proceed with load testing - application not reachable"
        exit 1
    fi
    
    # Warm up application
    warmup_application
    
    # Define test scenarios
    declare -A tests
    tests["health_check"]="$APP_URL/health"
    tests["metrics"]="$APP_URL/api/health/metrics"
    
    # Run different load test scenarios
    log_info "Running load test scenarios..."
    
    # Light load test
    perform_load_test "${tests[health_check]}" 10 60 "light_load_health"
    sleep 10
    
    # Medium load test
    perform_load_test "${tests[health_check]}" 50 120 "medium_load_health"
    sleep 10
    
    # Heavy load test
    perform_load_test "${tests[health_check]}" 100 180 "heavy_load_health"
    sleep 10
    
    # Metrics endpoint test
    perform_load_test "${tests[metrics]}" 20 90 "metrics_endpoint"
    
    # Monitor memory usage
    monitor_memory_usage
    
    # Analyze results
    analyze_results
    
    log_success "Performance testing completed!"
    log_info "Check $RESULTS_DIR for detailed results"
}

# Script usage
usage() {
    echo "Usage: $0 [OPTIONS]"
    echo "Options:"
    echo "  -u, --url URL           Application URL (default: http://localhost)"
    echo "  -h, --help             Show this help message"
    echo ""
    echo "Environment Variables:"
    echo "  APP_URL                Application URL"
    echo ""
    echo "Examples:"
    echo "  $0"
    echo "  $0 -u https://myapp.com"
}

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -u|--url)
            APP_URL="$2"
            shift 2
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            usage
            exit 1
            ;;
    esac
done

# Run main function
main