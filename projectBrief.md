# Project Brief: PDF İmzalama Uygulaması

## Overview

This project is a web-based PDF signing application that allows users to upload PDF files and digitally sign them using the Kolay İmza service. The application provides options for selecting the signature format (PadesBes or PadesT) and customizing the signature appearance (position and dimensions). It also supports multi-signature workflows, where multiple users can sign the same document.

## Purpose

The purpose of this application is to provide a user-friendly and secure way to digitally sign PDF documents. It aims to simplify the signing process and ensure the authenticity and integrity of the signed documents.

## Main Features

*   **PDF Upload:** Users can upload PDF files from their local devices.
*   **Signature Format Selection:** Users can choose between PadesBes (Basic Electronic Signature) and PadesT (Timestamped) signature formats.
*   **Signature Appearance Customization:** Users can customize the position and dimensions of the signature on the PDF document.
*   **Multi-Signature Support:** The application supports multi-signature workflows, allowing multiple users to sign the same document.
*   **Database Storage:** The application stores information about the signatures in a database, including the file name, signature format, certificate details, and signature data.
*   **Security:** The application implements security measures to protect against unauthorized access and ensure the integrity of the signed documents.
*   **Logging:** The application logs events and errors to help with debugging and troubleshooting.
*   **Admin Panel:** The application includes an admin panel for managing signature records and retrying failed signature processes.

## Technologies Used

*   PHP
*   JavaScript
*   Bootstrap
*   Font Awesome
*   TCPDF
*   MySQL
*   DataTables

## Database Schema

The application uses a MySQL database with a `signatures` table to store information about the digital signatures. The table includes columns for:

*   `id`: Unique identifier for the signature record.
*   `filename`: Name of the uploaded PDF file.
*   `original_filename`: Original name of the uploaded PDF file.
*   `file_size`: Size of the uploaded PDF file.
*   `signature_format`: Signature format (PadesBes or PadesT).
*   `certificate_name`: Name of the certificate used for signing.
*   `certificate_issuer`: Issuer of the certificate used for signing.
*   `certificate_serial_number`: Serial number of the certificate used for signing.
*   `signature_date`: Date and time of the signature.
*   `signature_location`: Location of the signature.
*   `signature_reason`: Reason for the signature.
*   `pdf_signature_pos_x`: X position of the signature on the PDF document.
*   `pdf_signature_pos_y`: Y position of the signature on the PDF document.
*   `pdf_signature_width`: Width of the signature on the PDF document.
*   `pdf_signature_height`: Height of the signature on the PDF document.
*   `signature_data`: Signature data.
*   `ip_address`: IP address of the user who signed the document.
*   `created_at`: Timestamp of when the signature record was created.
*   `status`: Status of the signature (pending, completed, failed).
*   `error_message`: Error message if the signature failed.
*   `signed_pdf_path`: Path to the signed PDF file.
*   `signature_chain`: JSON string containing the signature chain for multi-signature workflows.
*   `required_signatures`: Number of required signatures for multi-signature workflows.
*   `completed_signatures`: Number of completed signatures for multi-signature workflows.
*   `next_signer`: The next signer in the multi-signature workflow.
*   `signature_deadline`: The deadline for the signature.
*   `signature_groups`: JSON string containing the signature groups for multi-signature workflows.
*   `current_group`: The current group in the multi-signature workflow.
*   `group_signatures`: JSON string containing the signatures for each group in the multi-signature workflow.
*   `group_status`: JSON string containing the status for each group in the multi-signature workflow.

## Key Files

*   **index.php:** The main page of the PDF signing application, providing the user interface for uploading PDFs and initiating the signing process.
*   **sign.php:** Handles the PDF signing process, including validating the uploaded file, saving it to the server, creating a signature record in the database, and generating a sign protocol URL.
*   **verify.php:** Handles the verification of the digital signature, updating the signature chain in the database, and creating a signed PDF with the signature information added to the footer.
*   **create_test_pdf.php:** Allows the user to create a simple test PDF file using the TCPDF library.
*   **error.php:** Displays a user-friendly error page for common HTTP error codes.
*   **config.php:** Contains various configuration settings for the application, including database credentials, file paths, and security settings.
*   **includes/SecurityHelper.php:** Provides various security-related functions for the application, such as sanitizing inputs, validating data, and setting security headers.
*   **includes/Logger.php:** Provides logging functionality for the application.
*   **includes/SignatureManager.php:** Manages digital signatures in the database, providing methods for creating, retrieving, updating, and searching signature records.
*   **admin/auth.php:** Provides authentication and authorization functions for the admin panel. **Important Security Vulnerability:** The `validateAdminPassword()` function uses a hardcoded password (`admin123`) for authentication. **This should be replaced with a secure password hashing algorithm and a database lookup.**
*   **admin/login.php:** Provides the login page for the admin panel. **Important Security Vulnerability:** The script uses hardcoded credentials (`username === 'admin' && $password === 'admin123'`) for authentication. **This should be replaced with a secure password hashing algorithm and a database lookup.**
*   **admin/logout.php:** Handles the admin logout process.
*   **admin/retry.php:** Allows an administrator to retry a failed signature process.
*   **admin/signatures.php:** Displays a list of signature records with pagination and allows administrators to view details and retry failed signatures.
*   **database.sql:** Defines the database schema for the application.