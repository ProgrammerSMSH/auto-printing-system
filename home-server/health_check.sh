#!/bin/bash

# Remote Printing System - Health Check Script
# This script checks the health of all system components

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOG_FILE="${SCRIPT_DIR}/logs/health.log"
CONFIG_FILE="${SCRIPT_DIR}/config.php"
API_BASE_URL="https://your-domain.com"
API_KEY="your-secure-api-key-here"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Log function
log_message() {
    local message="$1"
    local level="$2"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    
    case "$level" in
        "ERROR")
            echo -e "${RED}[$timestamp] [ERROR] $message${NC}"
            ;;
        "WARNING")
            echo -e "${YELLOW}[$timestamp] [WARNING] $message${NC}"
            ;;
        "SUCCESS")
            echo -e "${GREEN}[$timestamp] [SUCCESS] $message${NC}"
            ;;
        *)
            echo "[$timestamp] [INFO] $message"
            ;;
    esac
    
    echo "[$timestamp] [$level] $message" >> "$LOG_FILE"
}

# Check if running as root
check_root() {
    if [[ $EUID -eq 0 ]]; then
        log_message "Running as root" "INFO"
        return 0
    else
        log_message "Not running as root (some checks may require root)" "WARNING"
        return 1
    fi
}

# Check disk space
check_disk_space() {
    local threshold=80
    local usage=$(df -h "${SCRIPT_DIR}" | awk 'NR==2 {print $5}' | sed 's/%//')
    
    if [[ $usage -ge $threshold ]]; then
        log_message "Disk usage is at ${usage}% (threshold: ${threshold}%)" "WARNING"
        return 1
    else
        log_message "Disk usage: ${usage}%" "SUCCESS"
        return 0
    fi
}

# Check CUPS service
check_cups() {
    if systemctl is-active --quiet cups; then
        log_message "CUPS service is running" "SUCCESS"
        
        # Check printers
        local printer_count=$(lpstat -p 2>/dev/null | grep -c "printer")
        if [[ $printer_count -gt 0 ]]; then
            log_message "Found $printer_count printer(s) in CUPS" "SUCCESS"
            
            # Check each printer status
            lpstat -p | while read -r line; do
                if [[ $line =~ printer\ ([^ ]+)\ .*is\ (.*)\. ]]; then
                    local printer="${BASH_REMATCH[1]}"
                    local status="${BASH_REMATCH[2]}"
                    
                    if [[ "$status" == "idle" ]] || [[ "$status" == "printing" ]]; then
                        log_message "Printer '$printer' status: $status" "SUCCESS"
                    else
                        log_message "Printer '$printer' status: $status" "WARNING"
                    fi
                fi
            done
        else
            log_message "No printers found in CUPS" "ERROR"
            return 1
        fi
    else
        log_message "CUPS service is not running" "ERROR"
        return 1
    fi
}

# Check cron service
check_cron() {
    if systemctl is-active --quiet cron; then
        log_message "Cron service is running" "SUCCESS"
        
        # Check if our cron job is scheduled
        if crontab -l 2>/dev/null | grep -q "cron.php"; then
            log_message "Print cron job is scheduled" "SUCCESS"
        else
            log_message "Print cron job is NOT scheduled in crontab" "ERROR"
            return 1
        fi
    else
        log_message "Cron service is not running" "ERROR"
        return 1
    fi
}

# Check API connectivity
check_api() {
    local endpoint="${API_BASE_URL}/api/print/pending"
    
    # Use curl with timeout
    local response=$(curl -s -w "%{http_code}" -H "X-API-Key: ${API_KEY}" \
        --connect-timeout 10 --max-time 30 "${endpoint}")
    
    local http_code="${response: -3}"
    local body="${response%???}"
    
    if [[ $http_code -eq 200 ]]; then
        log_message "API is reachable (HTTP 200)" "SUCCESS"
        
        # Parse JSON response
        if command -v jq >/dev/null 2>&1; then
            local status=$(echo "$body" | jq -r '.status')
            if [[ "$status" == "success" ]]; then
                log_message "API responded with success" "SUCCESS"
                return 0
            else
                log_message "API responded with error: $body" "WARNING"
                return 1
            fi
        else
            log_message "API responded with HTTP 200 (jq not installed for JSON parsing)" "SUCCESS"
            return 0
        fi
    else
        log_message "API is not reachable (HTTP $http_code)" "ERROR"
        return 1
    fi
}

# Check print queue
check_print_queue() {
    local queue_size=$(lpstat -o 2>/dev/null | wc -l)
    
    if [[ $queue_size -eq 0 ]]; then
        log_message "Print queue is empty" "SUCCESS"
    elif [[ $queue_size -le 10 ]]; then
        log_message "Print queue has $queue_size job(s)" "INFO"
    elif [[ $queue_size -le 50 ]]; then
        log_message "Print queue has $queue_size job(s)" "WARNING"
    else
        log_message "Print queue has $queue_size job(s) - may be stuck" "ERROR"
        return 1
    fi
}

# Check log files
check_logs() {
    local log_files=("cron.log" "print.log" "health.log")
    
    for log_file in "${log_files[@]}"; do
        local full_path="${SCRIPT_DIR}/logs/${log_file}"
        
        if [[ -f "$full_path" ]]; then
            local size=$(stat -c%s "$full_path" 2>/dev/null || stat -f%z "$full_path" 2>/dev/null)
            local lines=$(wc -l < "$full_path" 2>/dev/null || echo "0")
            
            if [[ $size -gt 10485760 ]]; then # 10MB
                log_message "Log file '$log_file' is large: $((size/1024/1024))MB" "WARNING"
            else
                log_message "Log file '$log_file': $lines lines, $((size/1024))KB" "INFO"
            fi
            
            # Check for recent errors
            if [[ "$log_file" == "cron.log" ]] || [[ "$log_file" == "print.log" ]]; then
                local recent_errors=$(grep -i "error\|failed" "$full_path" | tail -5)
                if [[ -n "$recent_errors" ]]; then
                    log_message "Recent errors in '$log_file':" "WARNING"
                    while IFS= read -r error_line; do
                        log_message "  $error_line" "WARNING"
                    done <<< "$recent_errors"
                fi
            fi
        else
            log_message "Log file '$log_file' not found" "WARNING"
        fi
    done
}

# Check Python script
check_python_script() {
    local script_path="${SCRIPT_DIR}/print_job.py"
    
    if [[ -f "$script_path" ]]; then
        if [[ -x "$script_path" ]]; then
            log_message "Python script exists and is executable" "SUCCESS"
            
            # Check Python dependencies
            if python3 -c "import cups, json, os, sys" 2>/dev/null; then
                log_message "Python dependencies are installed" "SUCCESS"
            else
                log_message "Python dependencies are missing" "ERROR"
                return 1
            fi
        else
            log_message "Python script exists but is not executable" "WARNING"
            chmod +x "$script_path" && log_message "Made script executable" "INFO"
        fi
    else
        log_message "Python script not found at $script_path" "ERROR"
        return 1
    fi
}

# Check PHP script
check_php_script() {
    local script_path="${SCRIPT_DIR}/cron.php"
    
    if [[ -f "$script_path" ]]; then
        log_message "PHP cron script exists" "SUCCESS"
        
        # Check PHP executable
        if command -v php >/dev/null 2>&1; then
            local php_version=$(php --version | head -1 | cut -d' ' -f2)
            log_message "PHP $php_version is installed" "SUCCESS"
            
            # Check PHP extensions
            local required_extensions=("curl" "json" "mbstring")
            for ext in "${required_extensions[@]}"; do
                if php -m | grep -q -i "^${ext}$"; then
                    log_message "PHP extension '$ext' is loaded" "SUCCESS"
                else
                    log_message "PHP extension '$ext' is NOT loaded" "ERROR"
                fi
            done
        else
            log_message "PHP is not installed" "ERROR"
            return 1
        fi
    else
        log_message "PHP cron script not found at $script_path" "ERROR"
        return 1
    fi
}

# Check configuration file
check_config() {
    if [[ -f "$CONFIG_FILE" ]]; then
        log_message "Configuration file exists" "SUCCESS"
        
        # Check if config.php is valid PHP
        if php -l "$CONFIG_FILE" >/dev/null 2>&1; then
            log_message "Configuration file has valid PHP syntax" "SUCCESS"
        else
            log_message "Configuration file has invalid PHP syntax" "ERROR"
            return 1
        fi
    else
        log_message "Configuration file not found at $CONFIG_FILE" "ERROR"
        return 1
    fi
}

# Check temp directory
check_temp_dir() {
    local temp_dir="${SCRIPT_DIR}/temp"
    
    if [[ -d "$temp_dir" ]]; then
        log_message "Temp directory exists" "SUCCESS"
        
        # Count files in temp directory
        local file_count=$(find "$temp_dir" -type f | wc -l)
        if [[ $file_count -eq 0 ]]; then
            log_message "Temp directory is empty" "SUCCESS"
        else
            log_message "Temp directory has $file_count file(s)" "INFO"
            
            # Check for old files
            local old_files=$(find "$temp_dir" -type f -mtime +1 | wc -l)
            if [[ $old_files -gt 0 ]]; then
                log_message "Found $old_files file(s) older than 1 day in temp directory" "WARNING"
            fi
        fi
    else
        log_message "Temp directory does not exist" "ERROR"
        mkdir -p "$temp_dir" && log_message "Created temp directory" "INFO"
    fi
}

# Main function
main() {
    log_message "Starting health check..." "INFO"
    log_message "Script directory: $SCRIPT_DIR" "INFO"
    
    local checks=(
        "check_root"
        "check_disk_space"
        "check_cups"
        "check_cron"
        "check_api"
        "check_print_queue"
        "check_python_script"
        "check_php_script"
        "check_config"
        "check_temp_dir"
        "check_logs"
    )
    
    local passed=0
    local failed=0
    local warnings=0
    
    for check in "${checks[@]}"; do
        log_message "Running check: $check" "INFO"
        
        if $check; then
            ((passed++))
        else
            case "$?" in
                1) ((failed++)) ;;
                *) ((warnings++)) ;;
            esac
        fi
        
        echo ""
    done
    
    # Summary
    log_message "Health check completed:" "INFO"
    log_message "  Passed: $passed" "INFO"
    log_message "  Warnings: $warnings" "INFO"
    log_message "  Failed: $failed" "INFO"
    
    if [[ $failed -eq 0 ]]; then
        log_message "All critical checks passed!" "SUCCESS"
        exit 0
    else
        log_message "$failed critical check(s) failed!" "ERROR"
        exit 1
    fi
}

# Run main function
main "$@"
