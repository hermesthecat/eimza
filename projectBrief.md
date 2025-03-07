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

## Technologies Used

*   PHP
*   JavaScript
*   Bootstrap
*   Font Awesome
*   TCPDF
*   MySQL

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