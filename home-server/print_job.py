#!/usr/bin/env python3
"""
Remote Printing System - Python Print Script

This script handles printing PDF files via CUPS.
It's called by the cron.php script with the following parameters:
    python3 print_job.py <filepath> <printer_name> <options_json>

Exit codes:
    0: Success
    1: Failure
"""

import cups
import sys
import json
import os
import time
from datetime import datetime
import traceback
import subprocess
import tempfile

def setup_logging():
    """Setup logging configuration"""
    log_dir = os.path.join(os.path.dirname(__file__), 'logs')
    if not os.path.exists(log_dir):
        os.makedirs(log_dir, exist_ok=True)
    
    log_file = os.path.join(log_dir, 'print.log')
    return log_file

def log_message(message, level="INFO"):
    """Log message to file with timestamp"""
    log_file = setup_logging()
    timestamp = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    log_entry = f"[{timestamp}] [{level}] {message}\n"
    
    try:
        with open(log_file, 'a') as f:
            f.write(log_entry)
    except Exception as e:
        print(f"Failed to write to log file: {e}")
        print(log_entry, end='')

def get_cups_connection():
    """Establish connection to CUPS server"""
    try:
        conn = cups.Connection()
        return conn
    except cups.IPPError as e:
        log_message(f"CUPS connection error: {e}", "ERROR")
        raise
    except Exception as e:
        log_message(f"Failed to connect to CUPS: {e}", "ERROR")
        raise

def validate_printer(conn, printer_name):
    """Validate that printer exists and is available"""
    try:
        printers = conn.getPrinters()
        
        if not printers:
            log_message("No printers found in CUPS", "ERROR")
            return False
        
        if printer_name not in printers:
            available_printers = ", ".join(printers.keys())
            log_message(f"Printer '{printer_name}' not found. Available printers: {available_printers}", "ERROR")
            return False
        
        printer_info = printers[printer_name]
        state = printer_info.get('printer-state', 'unknown')
        state_reasons = printer_info.get('printer-state-reasons', [])
        
        log_message(f"Printer '{printer_name}' found. State: {state}, Reasons: {state_reasons}")
        
        # Check if printer is idle or printing
        if state == cups.IPP_PRINTER_STOPPED:
            log_message(f"Printer '{printer_name}' is stopped", "WARNING")
            return False
        
        return True
        
    except Exception as e:
        log_message(f"Error validating printer: {e}", "ERROR")
        return False

def prepare_print_options(options):
    """Prepare CUPS print options from job settings"""
    print_options = {}
    
    # Paper size
    if options.get('paper_size'):
        paper_size = options['paper_size'].upper()
        # Map common paper sizes to CUPS media names
        media_map = {
            'A4': 'A4',
            'A3': 'A3',
            'LETTER': 'Letter',
            'LEGAL': 'Legal',
        }
        if paper_size in media_map:
            print_options['media'] = media_map[paper_size]
        else:
            print_options['media'] = paper_size
    
    # Color mode
    if options.get('color_mode') == 'grayscale':
        print_options['ColorModel'] = 'Gray'
    elif options.get('color_mode') == 'color':
        print_options['ColorModel'] = 'Color'
    
    # Copies
    if options.get('copies'):
        copies = int(options['copies'])
        if 1 <= copies <= 999:
            print_options['copies'] = str(copies)
    
    # Page range
    if options.get('page_range') and options['page_range'] != 'all':
        page_range = options['page_range']
        # Validate and format page range
        if '-' in page_range:
            try:
                start, end = page_range.split('-')
                start = int(start.strip())
                end = int(end.strip())
                if 1 <= start <= end <= 9999:
                    print_options['page-ranges'] = f"{start}-{end}"
            except ValueError:
                log_message(f"Invalid page range format: {page_range}", "WARNING")
        elif ',' in page_range:
            # Comma-separated pages
            pages = page_range.split(',')
            valid_pages = []
            for page in pages:
                try:
                    page_num = int(page.strip())
                    if 1 <= page_num <= 9999:
                        valid_pages.append(str(page_num))
                except ValueError:
                    continue
            if valid_pages:
                print_options['page-ranges'] = ','.join(valid_pages)
        else:
            # Single page
            try:
                page_num = int(page_range.strip())
                if 1 <= page_num <= 9999:
                    print_options['page-ranges'] = str(page_num)
            except ValueError:
                pass
    
    # Print quality
    print_options['print-quality'] = '3'  # Normal quality
    
    # Duplex printing (two-sided)
    # Uncomment if you want to enable duplex
    # print_options['sides'] = 'two-sided-long-edge'
    
    log_message(f"Print options prepared: {print_options}")
    return print_options

def validate_pdf_file(filepath):
    """Validate that file is a valid PDF"""
    if not os.path.exists(filepath):
        log_message(f"File does not exist: {filepath}", "ERROR")
        return False
    
    if not os.path.isfile(filepath):
        log_message(f"Path is not a file: {filepath}", "ERROR")
        return False
    
    # Check file size
    file_size = os.path.getsize(filepath)
    if file_size == 0:
        log_message(f"File is empty: {filepath}", "ERROR")
        return False
    
    if file_size > 50 * 1024 * 1024:  # 50MB limit
        log_message(f"File too large: {file_size} bytes", "ERROR")
        return False
    
    # Check PDF magic bytes
    try:
        with open(filepath, 'rb') as f:
            header = f.read(4)
            if header != b'%PDF':
                log_message(f"Invalid PDF file (wrong magic bytes): {header}", "ERROR")
                return False
    except Exception as e:
        log_message(f"Error reading file: {e}", "ERROR")
        return False
    
    return True

def print_pdf(filepath, printer_name, options):
    """Main function to print PDF via CUPS"""
    log_message(f"Starting print job: {filepath} on {printer_name}")
    
    # Validate PDF file
    if not validate_pdf_file(filepath):
        return False
    
    try:
        # Connect to CUPS
        conn = get_cups_connection()
        
        # Validate printer
        if not validate_printer(conn, printer_name):
            return False
        
        # Prepare print options
        print_options = prepare_print_options(options)
        
        # Get job title
        filename = os.path.basename(filepath)
        job_title = f"Remote Print: {filename}"
        
        # Submit print job
        log_message(f"Submitting print job to {printer_name}...")
        job_id = conn.printFile(
            printer_name,
            filepath,
            job_title,
            print_options
        )
        
        log_message(f"Print job submitted successfully. Job ID: {job_id}")
        
        # Wait for job to complete (optional)
        if options.get('wait_for_completion', False):
            wait_for_job_completion(conn, job_id, printer_name)
        
        return True
        
    except cups.IPPError as e:
        log_message(f"CUPS IPP error: {e}", "ERROR")
        return False
    except Exception as e:
        log_message(f"Print error: {e}", "ERROR")
        log_message(f"Traceback: {traceback.format_exc()}", "DEBUG")
        return False

def wait_for_job_completion(conn, job_id, printer_name, timeout=300):
    """Wait for print job to complete"""
    log_message(f"Waiting for job {job_id} to complete...")
    
    start_time = time.time()
    while time.time() - start_time < timeout:
        try:
            jobs = conn.getJobs(which_jobs='completed')
            
            # Check if our job is in completed list
            for job in jobs:
                if job == job_id:
                    job_info = jobs[job]
                    state = job_info.get('job-state', 'unknown')
                    
                    if state == cups.IPP_JOB_COMPLETED:
                        log_message(f"Job {job_id} completed successfully")
                        return True
                    elif state == cups.IPP_JOB_CANCELLED:
                        log_message(f"Job {job_id} was cancelled", "WARNING")
                        return False
                    elif state == cups.IPP_JOB_ABORTED:
                        log_message(f"Job {job_id} was aborted", "ERROR")
                        return False
        
        except Exception as e:
            log_message(f"Error checking job status: {e}", "WARNING")
        
        time.sleep(5)
    
    log_message(f"Timeout waiting for job {job_id}", "WARNING")
    return False

def check_cups_status():
    """Check CUPS server status"""
    try:
        # Check if CUPS service is running
        result = subprocess.run(['systemctl', 'is-active', 'cups'], 
                              capture_output=True, text=True)
        if result.returncode != 0:
            log_message("CUPS service is not running", "ERROR")
            return False
        
        # Check printer status
        conn = get_cups_connection()
        printers = conn.getPrinters()
        
        if not printers:
            log_message("No printers configured in CUPS", "WARNING")
            return False
        
        log_message(f"CUPS is running. Found {len(printers)} printer(s)")
        for printer_name, printer_info in printers.items():
            state = printer_info.get('printer-state', 'unknown')
            log_message(f"  - {printer_name}: state={state}")
        
        return True
        
    except Exception as e:
        log_message(f"Error checking CUPS status: {e}", "ERROR")
        return False

def main():
    """Main function"""
    if len(sys.argv) < 4:
        print("Usage: python3 print_job.py <filepath> <printer_name> <options_json>")
        log_message("Invalid arguments. Expected: <filepath> <printer_name> <options_json>", "ERROR")
        sys.exit(1)
    
    filepath = sys.argv[1]
    printer_name = sys.argv[2]
    options_json = sys.argv[3]
    
    # Parse options
    try:
        options = json.loads(options_json)
    except json.JSONDecodeError as e:
        log_message(f"Invalid JSON options: {e}", "ERROR")
        sys.exit(1)
    
    # Log startup
    log_message(f"Starting print script with arguments:")
    log_message(f"  File: {filepath}")
    log_message(f"  Printer: {printer_name}")
    log_message(f"  Options: {options}")
    
    # Check CUPS status
    if not check_cups_status():
        log_message("CUPS check failed, aborting print job", "ERROR")
        sys.exit(1)
    
    # Print the PDF
    success = print_pdf(filepath, printer_name, options)
    
    if success:
        log_message("Print job completed successfully")
        sys.exit(0)
    else:
        log_message("Print job failed", "ERROR")
        sys.exit(1)

if __name__ == "__main__":
    main()
