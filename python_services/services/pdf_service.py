"""
PDF Processing Service

Handles PDF operations including:
- Converting PDF pages to images
- Merging multiple PDFs
- Extracting text from PDFs
- PDF metadata extraction
"""

import io
import os
import base64
from typing import List, Dict, Any
from pathlib import Path

try:
    from PyPDF2 import PdfReader, PdfWriter
    from pdf2image import convert_from_bytes, convert_from_path
    from PIL import Image
    import fitz  # PyMuPDF for advanced PDF operations
except ImportError as e:
    print(f"Warning: PDF dependencies not installed: {e}")

from utils.logger import setup_logger

logger = setup_logger(__name__, 'pdf_service.log')


class PDFService:
    """Service for PDF processing operations."""
    
    def __init__(self):
        self.max_dpi = 300
        self.default_dpi = 150
        self.max_file_size = 50 * 1024 * 1024  # 50MB limit
        logger.info("PDF Service initialized")
    
    def convert_to_images(
        self,
        pdf_content: bytes,
        filename: str,
        dpi: int = None,
        format: str = 'PNG'
    ) -> Dict[str, Any]:
        """
        Convert PDF pages to images.
        
        Args:
            pdf_content: PDF file content as bytes
            filename: Original filename
            dpi: Resolution (default: 150)
            format: Image format (PNG, JPEG)
        
        Returns:
            Dict with success status and image data
        """
        try:
            dpi = dpi or self.default_dpi
            dpi = min(dpi, self.max_dpi)  # Cap at max DPI
            
            logger.info(f"Converting PDF to images: {filename}, DPI: {dpi}")
            
            # Convert PDF to images
            images = convert_from_bytes(
                pdf_content,
                dpi=dpi,
                fmt=format.lower()
            )
            
            # Convert images to base64
            image_data = []
            for i, image in enumerate(images):
                buffer = io.BytesIO()
                image.save(buffer, format=format)
                img_base64 = base64.b64encode(buffer.getvalue()).decode('utf-8')
                
                image_data.append({
                    'page': i + 1,
                    'format': format,
                    'width': image.width,
                    'height': image.height,
                    'data': img_base64
                })
            
            logger.info(f"Successfully converted {len(images)} pages to images")
            
            return {
                'success': True,
                'filename': filename,
                'total_pages': len(images),
                'dpi': dpi,
                'format': format,
                'images': image_data
            }
            
        except Exception as e:
            logger.error(f"Error converting PDF to images: {str(e)}")
            return {
                'success': False,
                'error': str(e),
                'filename': filename
            }
    
    def merge_pdfs(self, pdf_contents: List[bytes]) -> Dict[str, Any]:
        """
        Merge multiple PDF files into one.
        
        Args:
            pdf_contents: List of PDF file contents as bytes
        
        Returns:
            Dict with success status and merged PDF data
        """
        try:
            logger.info(f"Merging {len(pdf_contents)} PDF files")
            
            writer = PdfWriter()
            
            # Add all pages from all PDFs
            for pdf_bytes in pdf_contents:
                reader = PdfReader(io.BytesIO(pdf_bytes))
                for page in reader.pages:
                    writer.add_page(page)
            
            # Write to bytes
            output_buffer = io.BytesIO()
            writer.write(output_buffer)
            merged_pdf = output_buffer.getvalue()
            
            # Convert to base64 for JSON response
            pdf_base64 = base64.b64encode(merged_pdf).decode('utf-8')
            
            logger.info(f"Successfully merged {len(pdf_contents)} PDFs")
            
            return {
                'success': True,
                'total_files': len(pdf_contents),
                'total_pages': len(writer.pages),
                'data': pdf_base64
            }
            
        except Exception as e:
            logger.error(f"Error merging PDFs: {str(e)}")
            return {
                'success': False,
                'error': str(e)
            }
    
    def extract_text(self, pdf_content: bytes) -> Dict[str, Any]:
        """
        Extract text from PDF.
        
        Args:
            pdf_content: PDF file content as bytes
        
        Returns:
            Dict with success status and extracted text
        """
        try:
            logger.info("Extracting text from PDF")
            
            reader = PdfReader(io.BytesIO(pdf_content))
            
            text_by_page = []
            for i, page in enumerate(reader.pages):
                text = page.extract_text()
                text_by_page.append({
                    'page': i + 1,
                    'text': text
                })
            
            all_text = '\n\n'.join([p['text'] for p in text_by_page])
            
            logger.info(f"Extracted text from {len(reader.pages)} pages")
            
            return {
                'success': True,
                'total_pages': len(reader.pages),
                'pages': text_by_page,
                'full_text': all_text
            }
            
        except Exception as e:
            logger.error(f"Error extracting text from PDF: {str(e)}")
            return {
                'success': False,
                'error': str(e)
            }
    
    def convert_page_to_image(
        self,
        file_path: str,
        page_number: int,
        resolution: int = None
    ) -> Dict[str, Any]:
        """
        Convert a single PDF page to image.
        
        Args:
            file_path: Absolute path to PDF file
            page_number: Page number (1-based)
            resolution: DPI resolution (default: 150)
        
        Returns:
            Dict with success status and image data
        """
        try:
            resolution = resolution or self.default_dpi
            resolution = min(resolution, self.max_dpi)
            
            if not Path(file_path).exists():
                logger.error(f"File not found: {file_path}")
                return {
                    'success': False,
                    'error': f'File not found: {file_path}'
                }
            
            logger.info(f"Converting page {page_number} of {file_path} at {resolution} DPI")
            
            # Use PyMuPDF for single page conversion (faster)
            try:
                with fitz.open(file_path) as doc:
                    if page_number > doc.page_count or page_number < 1:
                        return {
                            'success': False,
                            'error': f'Page {page_number} exceeds document length ({doc.page_count})'
                        }
                    
                    page = doc.load_page(page_number - 1)  # 0-based indexing
                    
                    # Calculate zoom for desired resolution
                    zoom = resolution / 72  # 72 DPI is default
                    mat = fitz.Matrix(zoom, zoom)
                    
                    # Render page to pixmap
                    pix = page.get_pixmap(matrix=mat)
                    
                    # Convert to bytes
                    img_data = pix.tobytes("png")
                    
                    # Encode to base64
                    img_base64 = base64.b64encode(img_data).decode('utf-8')
                    
                    logger.info(f"Successfully converted page {page_number}")
                    
                    return {
                        'success': True,
                        'image_data': f'data:image/png;base64,{img_base64}',
                        'page_number': page_number,
                        'resolution': resolution,
                        'width': pix.width,
                        'height': pix.height
                    }
            
            except Exception as fitz_error:
                # Fallback to pdf2image
                logger.warning(f"PyMuPDF failed, falling back to pdf2image: {fitz_error}")
                
                with open(file_path, 'rb') as f:
                    pdf_bytes = f.read()
                
                images = convert_from_bytes(
                    pdf_bytes,
                    dpi=resolution,
                    first_page=page_number,
                    last_page=page_number
                )
                
                if not images:
                    raise Exception("No images generated")
                
                image = images[0]
                buffer = io.BytesIO()
                image.save(buffer, format='PNG')
                img_base64 = base64.b64encode(buffer.getvalue()).decode('utf-8')
                
                return {
                    'success': True,
                    'image_data': f'data:image/png;base64,{img_base64}',
                    'page_number': page_number,
                    'resolution': resolution,
                    'width': image.width,
                    'height': image.height
                }
        
        except Exception as e:
            logger.error(f"Error converting page {page_number}: {str(e)}")
            return {
                'success': False,
                'error': str(e)
            }
    
    def get_pdf_info(self, file_path: str) -> Dict[str, Any]:
        """
        Get PDF information including page count and metadata.
        
        Args:
            file_path: Absolute path to PDF file
        
        Returns:
            Dict with PDF information
        """
        try:
            if not Path(file_path).exists():
                return {
                    'success': False,
                    'error': f'File not found: {file_path}'
                }
            
            logger.info(f"Getting PDF info for: {file_path}")
            
            # Try PyMuPDF first (more detailed info)
            try:
                with fitz.open(file_path) as doc:
                    info = {
                        'success': True,
                        'page_count': doc.page_count,
                        'metadata': doc.metadata,
                        'pages': []
                    }
                    
                    for page_num in range(doc.page_count):
                        page = doc.load_page(page_num)
                        rect = page.rect
                        info['pages'].append({
                            'page_number': page_num + 1,
                            'width': rect.width,
                            'height': rect.height,
                            'rotation': page.rotation
                        })
                    
                    return info
            
            except Exception as fitz_error:
                # Fallback to PyPDF2
                logger.warning(f"PyMuPDF failed, using PyPDF2: {fitz_error}")
                
                reader = PdfReader(file_path)
                
                return {
                    'success': True,
                    'page_count': len(reader.pages),
                    'metadata': reader.metadata if reader.metadata else {},
                    'pages': [
                        {
                            'page_number': i + 1,
                            'width': float(reader.pages[i].mediabox.width),
                            'height': float(reader.pages[i].mediabox.height),
                            'rotation': int(reader.pages[i].get('/Rotate', 0))
                        }
                        for i in range(len(reader.pages))
                    ]
                }
        
        except Exception as e:
            logger.error(f"Error getting PDF info: {str(e)}")
            return {
                'success': False,
                'error': str(e)
            }
    
    def validate_pdf(self, file_path: str) -> Dict[str, Any]:
        """
        Validate a PDF file.
        
        Args:
            file_path: Absolute path to PDF file
        
        Returns:
            Dict with validation result
        """
        try:
            if not Path(file_path).exists():
                return {
                    'valid': False,
                    'error': 'File not found'
                }
            
            file_size = Path(file_path).stat().st_size
            if file_size > self.max_file_size:
                return {
                    'valid': False,
                    'error': f'File too large: {file_size} bytes (max: {self.max_file_size})'
                }
            
            # Try to open with PyMuPDF
            try:
                with fitz.open(file_path) as doc:
                    if doc.page_count == 0:
                        return {
                            'valid': False,
                            'error': 'PDF has no pages'
                        }
                    
                    return {
                        'valid': True,
                        'page_count': doc.page_count,
                        'file_size': file_size
                    }
            
            except Exception as fitz_error:
                # Try PyPDF2 as fallback
                reader = PdfReader(file_path)
                if len(reader.pages) == 0:
                    return {
                        'valid': False,
                        'error': 'PDF has no pages'
                    }
                
                return {
                    'valid': True,
                    'page_count': len(reader.pages),
                    'file_size': file_size
                }
        
        except Exception as e:
            logger.error(f"PDF validation failed: {str(e)}")
            return {
                'valid': False,
                'error': str(e)
            }
    
    def normalize_pdf(self, input_path: str, output_path: str) -> Dict[str, Any]:
        """
        Normalize PDF for better compatibility.
        
        Args:
            input_path: Input PDF path
            output_path: Output PDF path
        
        Returns:
            Dict with success status
        """
        try:
            if not Path(input_path).exists():
                return {
                    'success': False,
                    'error': 'Input file not found'
                }
            
            logger.info(f"Normalizing PDF: {input_path} -> {output_path}")
            
            # Use PyMuPDF for normalization
            with fitz.open(input_path) as doc:
                # Create new document
                new_doc = fitz.open()
                
                for page_num in range(doc.page_count):
                    page = doc.load_page(page_num)
                    new_doc.insert_pdf(doc, from_page=page_num, to_page=page_num)
                
                # Save normalized PDF
                new_doc.save(output_path, garbage=4, deflate=True)
                new_doc.close()
            
            logger.info(f"PDF normalized successfully: {output_path}")
            
            return {
                'success': True,
                'input_path': input_path,
                'output_path': output_path
            }
        
        except Exception as e:
            logger.error(f"PDF normalization failed: {str(e)}")
            return {
                'success': False,
                'error': str(e)
            }
    
    def add_signatures_to_pdf(
        self,
        input_path: str,
        output_path: str,
        signatures: List[Dict[str, Any]]
    ) -> Dict[str, Any]:
        """
        Add signatures to PDF at specified positions.
        
        Args:
            input_path: Input PDF path
            output_path: Output PDF path
            signatures: List of signature data with positions
        
        Returns:
            Dict with success status
        """
        try:
            if not Path(input_path).exists():
                return {
                    'success': False,
                    'error': 'Input file not found'
                }
            
            logger.info(f"Adding {len(signatures)} signatures to PDF")
            
            with fitz.open(input_path) as doc:
                # Group signatures by page
                signatures_by_page = {}
                for sig in signatures:
                    page_num = sig.get('page_number', 1)
                    if page_num not in signatures_by_page:
                        signatures_by_page[page_num] = []
                    signatures_by_page[page_num].append(sig)
                
                # Process each page
                for page_num in range(doc.page_count):
                    page = doc.load_page(page_num)
                    current_page = page_num + 1
                    
                    if current_page in signatures_by_page:
                        for sig in signatures_by_page[current_page]:
                            try:
                                # Get signature image data
                                sig_data = sig.get('signature_data', '')
                                
                                # Handle data URI format
                                if ',' in sig_data:
                                    signature_bytes = base64.b64decode(sig_data.split(',')[1])
                                else:
                                    signature_bytes = base64.b64decode(sig_data)
                                
                                # Create temporary signature file
                                temp_sig_path = f"temp/sig_{sig.get('field_id', 0)}_{os.getpid()}.png"
                                Path(temp_sig_path).parent.mkdir(exist_ok=True)
                                
                                with open(temp_sig_path, 'wb') as f:
                                    f.write(signature_bytes)
                                
                                # Calculate signature position
                                rect = page.rect
                                x_pos = sig.get('x_percent', 0) * rect.width
                                y_pos = sig.get('y_percent', 0) * rect.height
                                width = sig.get('width_percent', 0.2) * rect.width
                                height = sig.get('height_percent', 0.1) * rect.height
                                
                                # Insert signature image
                                page.insert_image(
                                    fitz.Rect(x_pos, y_pos, x_pos + width, y_pos + height),
                                    filename=temp_sig_path
                                )
                                
                                # Clean up temp file
                                if Path(temp_sig_path).exists():
                                    Path(temp_sig_path).unlink()
                            
                            except Exception as e:
                                logger.error(f"Failed to add signature: {str(e)}")
                                continue
                
                # Save signed PDF
                doc.save(output_path, garbage=4, deflate=True)
            
            logger.info(f"Signatures added successfully: {output_path}")
            
            return {
                'success': True,
                'input_path': input_path,
                'output_path': output_path,
                'signatures_count': len(signatures)
            }
        
        except Exception as e:
            logger.error(f"Failed to add signatures: {str(e)}")
            return {
                'success': False,
                'error': str(e)
            }
    
    def batch_convert_pages(
        self,
        file_path: str,
        pages: List[int],
        resolution: int = None
    ) -> Dict[str, Any]:
        """
        Convert multiple PDF pages to images in batch.
        
        Args:
            file_path: Absolute path to PDF file
            pages: List of page numbers to convert
            resolution: DPI resolution
        
        Returns:
            Dict with results for each page
        """
        try:
            resolution = resolution or self.default_dpi
            resolution = min(resolution, self.max_dpi)
            
            if not Path(file_path).exists():
                return {
                    'success': False,
                    'error': 'File not found'
                }
            
            logger.info(f"Batch converting {len(pages)} pages at {resolution} DPI")
            
            results = {}
            for page_num in pages:
                result = self.convert_page_to_image(file_path, page_num, resolution)
                if result.get('success'):
                    results[str(page_num)] = result['image_data']
                else:
                    results[str(page_num)] = None
            
            return {
                'success': True,
                'results': results,
                'total_pages': len(pages)
            }
        
        except Exception as e:
            logger.error(f"Batch conversion failed: {str(e)}")
            return {
                'success': False,
                'error': str(e)
            }

