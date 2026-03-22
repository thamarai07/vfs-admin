<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dynamic Banner Creation System</title>
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #8b5cf6;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --dark: #1f2937;
            --light: #f9fafb;
            --gray: #6b7280;
            --border: #e5e7eb;
            --shadow: rgba(0, 0, 0, 0.1);
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: var(--dark);
        }
        .container {
            max-width: 1800px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 20px 30px;
            border-radius: 16px;
            margin-bottom: 24px;
            box-shadow: 0 8px 32px var(--shadow);
        }
        .header h1 {
            font-size: clamp(1.5rem, 3vw, 2rem);
            color: var(--primary);
            margin-bottom: 8px;
        }
        .header p {
            color: var(--gray);
            font-size: clamp(0.875rem, 2vw, 1rem);
        }
        .main-grid {
            display: grid;
            grid-template-columns: 450px 1fr;
            gap: 24px;
            align-items: start;
        }
        .form-panel {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 8px 32px var(--shadow);
            max-height: calc(100vh - 140px);
            overflow-y: auto;
            position: sticky;
            top: 20px;
        }
        .form-panel::-webkit-scrollbar {
            width: 8px;
        }
        .form-panel::-webkit-scrollbar-track {
            background: var(--light);
            border-radius: 10px;
        }
        .form-panel::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 10px;
        }
        .form-section {
            margin-bottom: 24px;
            padding-bottom: 24px;
            border-bottom: 1px solid var(--border);
        }
        .form-section:last-child {
            border-bottom: none;
        }
        .section-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 6px;
        }
        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 0.875rem;
            transition: all 0.3s;
            background: white;
        }
        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        .form-textarea {
            resize: vertical;
            min-height: 80px;
        }
        .color-input-group {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .color-input-group input[type="color"] {
            width: 50px;
            height: 40px;
            border: 2px solid var(--border);
            border-radius: 8px;
            cursor: pointer;
        }
        .slider-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .slider-value {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.875rem;
            color: var(--gray);
        }
        .slider-value span {
            font-weight: 600;
            color: var(--primary);
        }
        input[type="range"] {
            width: 100%;
            height: 6px;
            border-radius: 5px;
            background: var(--border);
            outline: none;
            -webkit-appearance: none;
        }
        input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: var(--primary);
            cursor: pointer;
            transition: all 0.3s;
        }
        input[type="range"]::-webkit-slider-thumb:hover {
            transform: scale(1.2);
        }
        .spacing-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
        }
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 26px;
        }
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: var(--gray);
            transition: 0.4s;
            border-radius: 34px;
        }
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: 0.4s;
            border-radius: 50%;
        }
        input:checked + .toggle-slider {
            background-color: var(--primary);
        }
        input:checked + .toggle-slider:before {
            transform: translateX(24px);
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            justify-content: center;
        }
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
        }
        .btn-success {
            background: var(--success);
            color: white;
        }
        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
        }
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        .btn-secondary {
            background: var(--gray);
            color: white;
        }
        .btn-block {
            width: 100%;
        }
        .cta-builder {
            border: 2px dashed var(--border);
            padding: 16px;
            border-radius: 8px;
            margin-top: 8px;
        }
        .cta-item {
            background: var(--light);
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 12px;
            position: relative;
        }
        .cta-remove {
            position: absolute;
            top: 8px;
            right: 8px;
            background: var(--danger);
            color: white;
            border: none;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 14px;
        }
        .preview-panel {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 8px 32px var(--shadow);
        }
        .preview-controls {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .preview-mode {
            display: flex;
            gap: 8px;
            background: var(--light);
            padding: 4px;
            border-radius: 8px;
        }
        .preview-mode button {
            padding: 8px 16px;
            border: none;
            background: transparent;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }
        .preview-mode button.active {
            background: white;
            box-shadow: 0 2px 8px var(--shadow);
            color: var(--primary);
        }
        .preview-area {
            border: 2px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            background: white;
            min-height: 400px;
            transition: all 0.3s;
        }
        .preview-area.mobile {
            max-width: 375px;
            margin: 0 auto;
        }
        .preview-area.tablet {
            max-width: 768px;
            margin: 0 auto;
        }
        .banner-section {
            margin-bottom: 24px;
        }
        .section-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray);
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .banner-grid {
            display: grid;
            gap: 16px;
        }
        .banner-grid.cols-1 {
            grid-template-columns: 1fr;
        }
        .banner-grid.cols-2 {
            grid-template-columns: repeat(2, 1fr);
        }
        .banner-grid.cols-3 {
            grid-template-columns: repeat(3, 1fr);
        }
        .banner-grid.cols-4 {
            grid-template-columns: repeat(4, 1fr);
        }
        .banner-grid.layout-8-4 {
            grid-template-columns: 2fr 1fr;
        }
        .banner-grid.layout-6-6 {
            grid-template-columns: 1fr 1fr;
        }
        .banner-grid.layout-4-4-4 {
            grid-template-columns: repeat(3, 1fr);
        }
        .banner-grid.layout-3-3-3-3 {
            grid-template-columns: repeat(4, 1fr);
        }
        .banner-grid.layout-50-50 {
            grid-template-columns: 1fr 1fr;
        }
        .banner-grid.layout-30-70 {
            grid-template-columns: 30% 70%;
        }
        .banner-grid.layout-70-30 {
            grid-template-columns: 70% 30%;
        }
        .banner-grid.layout-33-33-33 {
            grid-template-columns: repeat(3, 1fr);
        }
        .banner-grid.layout-25-50-25 {
            grid-template-columns: 1fr 2fr 1fr;
        }
        @media (max-width: 1024px) {
            .banner-grid {
                grid-template-columns: repeat(2, 1fr) !important;
            }
        }
        @media (max-width: 768px) {
            .banner-grid {
                grid-template-columns: 1fr !important;
            }
        }
        .banner-item {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s;
            background-size: cover;
            background-position: center;
            min-height: 200px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 24px;
        }
        .banner-item:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px var(--shadow);
        }
        .banner-item.inactive {
            opacity: 0.5;
            filter: grayscale(1);
        }
        .banner-content {
            position: relative;
            z-index: 2;
        }
        .banner-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .banner-title {
            font-size: clamp(1.25rem, 3vw, 1.75rem);
            font-weight: 700;
            margin-bottom: 8px;
            line-height: 1.2;
        }
        .banner-subtitle {
            font-size: clamp(1rem, 2vw, 1.25rem);
            font-weight: 500;
            margin-bottom: 8px;
            opacity: 0.9;
        }
        .banner-description {
            font-size: clamp(0.875rem, 1.5vw, 1rem);
            margin-bottom: 16px;
            opacity: 0.8;
        }
        .banner-ctas {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .banner-cta {
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        .banner-cta:hover {
            transform: scale(1.05);
        }
        .banner-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: 1;
        }
        .json-output {
            background: var(--dark);
            color: #a3e635;
            padding: 20px;
            border-radius: 12px;
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            max-height: 400px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-break: break-all;
            margin-top: 20px;
        }
        .action-buttons {
            display: flex;
            gap: 12px;
            margin-top: 16px;
            flex-wrap: wrap;
        }
        .gradient-builder {
            display: grid;
            grid-template-columns: 1fr 1fr 80px;
            gap: 8px;
            margin-top: 8px;
        }
        .shadow-builder {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            margin-top: 8px;
        }
        .file-upload-wrapper {
            position: relative;
            margin-top: 8px;
        }
        .file-upload-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px;
            border: 2px dashed var(--border);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            background: var(--light);
        }
        .file-upload-label:hover {
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.05);
        }
        .file-upload-input {
            display: none;
        }
        .image-preview {
            margin-top: 8px;
            border-radius: 8px;
            max-width: 100%;
            max-height: 150px;
            object-fit: cover;
        }
        @media (max-width: 1400px) {
            .main-grid {
                grid-template-columns: 400px 1fr;
            }
        }
        @media (max-width: 1024px) {
            .main-grid {
                grid-template-columns: 1fr;
            }
            .form-panel {
                position: static;
                max-height: none;
            }
        }
        .banner-manager {
            margin-top: 24px;
            padding: 20px;
            background: var(--light);
            border-radius: 12px;
        }
        .banner-list-item {
            background: white;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: move;
            transition: all 0.3s;
        }
        .banner-list-item:hover {
            box-shadow: 0 4px 12px var(--shadow);
        }
        .banner-list-item.dragging {
            opacity: 0.5;
        }
        .banner-info {
            flex: 1;
        }
        .banner-actions {
            display: flex;
            gap: 8px;
        }
        .icon-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px;
            border-radius: 6px;
            transition: all 0.3s;
        }
        .icon-btn:hover {
            background: var(--light);
        }
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .banner-item.animate-fade {
            animation: fadeIn 0.5s ease-out;
        }
        @keyframes scaleUp {
            from {
                transform: scale(0.95);
            }
            to {
                transform: scale(1);
            }
        }
        .banner-item.animate-scale:hover {
            animation: scaleUp 0.3s ease-out;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎨 Dynamic Banner Creation System</h1>
            <p>Create stunning, responsive banners with live preview and JSON export</p>
        </div>
        <div class="main-grid">
            <!-- Form Panel -->
            <div class="form-panel">
                <form id="bannerForm">
                    <!-- Basic Info -->
                    <div class="form-section">
                        <div class="section-title">📝 Basic Information</div>
                       
                        <div class="form-group">
                            <label class="form-label">Group Name</label>
                            <input type="text" class="form-input" id="groupName" placeholder="Enter group name (e.g., Summer Campaign)">
                        </div>
                       
                        <div class="form-group">
                            <label class="form-label">Banner Title</label>
                            <input type="text" class="form-input" id="bannerTitle" placeholder="Enter banner title">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Banner Sub-Title</label>
                            <input type="text" class="form-input" id="bannerSubtitle" placeholder="Enter subtitle">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Description / Tagline</label>
                            <textarea class="form-textarea" id="bannerDescription" placeholder="Enter description"></textarea>
                        </div>
                    </div>
                    <!-- Section & Layout -->
                    <div class="form-section">
                        <div class="section-title">📐 Section & Layout</div>
                       
                        <div class="form-group">
                            <label class="form-label">Banner Section</label>
                            <select class="form-select" id="bannerSection">
                                <option value="top">Top</option>
                                <option value="middle">Middle</option>
                                <option value="bottom">Bottom</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Layout</label>
                            <select class="form-select" id="bannerLayout">
                                <option value="cols-1">Full Width (12)</option>
                                <option value="layout-8-4">8 + 4 Columns</option>
                                <option value="layout-6-6">6 + 6 Columns</option>
                                <option value="layout-4-4-4">4 + 4 + 4 Columns</option>
                                <option value="layout-3-3-3-3">3 + 3 + 3 + 3 Columns</option>
                            </select>
                        </div>
                    </div>
                    <!-- Images -->
                    <div class="form-section">
                        <div class="section-title">🖼️ Banner Images</div>
                       
                        <div class="form-group">
                            <label class="form-label">Desktop Image</label>
                            <div class="file-upload-wrapper">
                                <label class="file-upload-label" for="desktopImage">
                                    📁 Choose Desktop Image
                                </label>
                                <input type="file" class="file-upload-input" id="desktopImage" accept="image/*">
                            </div>
                            <img id="desktopImagePreview" class="image-preview" style="display:none;">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Mobile Image</label>
                            <div class="file-upload-wrapper">
                                <label class="file-upload-label" for="mobileImage">
                                    📁 Choose Mobile Image
                                </label>
                                <input type="file" class="file-upload-input" id="mobileImage" accept="image/*">
                            </div>
                            <img id="mobileImagePreview" class="image-preview" style="display:none;">
                        </div>
                    </div>
                    <!-- Background -->
                    <div class="form-section">
                        <div class="section-title">🎨 Background</div>
                       
                        <div class="form-group">
                            <label class="form-label">Background Type</label>
                            <select class="form-select" id="bgType">
                                <option value="solid">Solid Color</option>
                                <option value="gradient">Gradient</option>
                                <option value="image">Image</option>
                            </select>
                        </div>
                        <div class="form-group" id="bgSolidGroup">
                            <label class="form-label">Background Color</label>
                            <div class="color-input-group">
                                <input type="color" id="bgColor" value="#6366f1">
                                <input type="text" class="form-input" id="bgColorText" value="#6366f1">
                            </div>
                        </div>
                        <div class="form-group" id="bgGradientGroup" style="display:none;">
                            <label class="form-label">Gradient Colors</label>
                            <div class="gradient-builder">
                                <input type="color" id="gradientColor1" value="#6366f1">
                                <input type="color" id="gradientColor2" value="#8b5cf6">
                                <input type="number" class="form-input" id="gradientAngle" value="135" min="0" max="360" placeholder="Angle">
                            </div>
                        </div>
                        <div class="form-group" id="bgImageGroup" style="display:none;">
                            <label class="form-label">Background Image</label>
                            <div class="file-upload-wrapper">
                                <label class="file-upload-label" for="bgImage">
                                    📁 Choose Background Image
                                </label>
                                <input type="file" class="file-upload-input" id="bgImage" accept="image/*">
                            </div>
                        </div>
                    </div>
                    <!-- Text Color -->
                    <div class="form-section">
                        <div class="section-title">✏️ Text Color</div>
                       
                        <div class="form-group">
                            <label class="form-label">Text Color</label>
                            <div class="color-input-group">
                                <input type="color" id="textColor" value="#ffffff">
                                <input type="text" class="form-input" id="textColorText" value="#ffffff">
                            </div>
                        </div>
                    </div>
                    <!-- CTA Buttons -->
                    <div class="form-section">
                        <div class="section-title">🔘 CTA Buttons</div>
                       
                        <div id="ctaContainer"></div>
                       
                        <button type="button" class="btn btn-secondary btn-block" id="addCtaBtn">
                            + Add CTA Button
                        </button>
                    </div>
                    <!-- Styling -->
                    <div class="form-section">
                        <div class="section-title">🎭 Styling</div>
                       
                        <div class="form-group">
                            <label class="form-label">Border Radius</label>
                            <div class="slider-group">
                                <input type="range" id="borderRadius" min="0" max="60" value="12">
                                <div class="slider-value">
                                    <span>Border Radius</span>
                                    <span id="borderRadiusValue">12px</span>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Box Shadow</label>
                            <div class="shadow-builder">
                                <input type="number" class="form-input" id="shadowX" value="0" placeholder="X Offset">
                                <input type="number" class="form-input" id="shadowY" value="4" placeholder="Y Offset">
                                <input type="number" class="form-input" id="shadowBlur" value="12" placeholder="Blur">
                                <input type="number" class="form-input" id="shadowSpread" value="0" placeholder="Spread">
                                <input type="color" id="shadowColor" value="#000000">
                                <input type="number" class="form-input" id="shadowOpacity" value="0.1" step="0.1" min="0" max="1" placeholder="Opacity">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Padding (px)</label>
                            <div class="spacing-grid">
                                <input type="number" class="form-input" id="paddingTop" value="24" placeholder="Top">
                                <input type="number" class="form-input" id="paddingRight" value="24" placeholder="Right">
                                <input type="number" class="form-input" id="paddingBottom" value="24" placeholder="Bottom">
                                <input type="number" class="form-input" id="paddingLeft" value="24" placeholder="Left">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Margin (px)</label>
                            <div class="spacing-grid">
                                <input type="number" class="form-input" id="marginTop" value="0" placeholder="Top">
                                <input type="number" class="form-input" id="marginRight" value="0" placeholder="Right">
                                <input type="number" class="form-input" id="marginBottom" value="0" placeholder="Bottom">
                                <input type="number" class="form-input" id="marginLeft" value="0" placeholder="Left">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Z-Index</label>
                            <input type="number" class="form-input" id="zIndex" value="1" min="0">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Opacity</label>
                            <div class="slider-group">
                                <input type="range" id="opacity" min="0" max="1" step="0.1" value="1">
                                <div class="slider-value">
                                    <span>Opacity</span>
                                    <span id="opacityValue">100%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Badge -->
                    <div class="form-section">
                        <div class="section-title">🏷️ Badge / Tag</div>
                       
                        <div class="form-group">
                            <label class="form-label">Badge Text</label>
                            <input type="text" class="form-input" id="badgeText" placeholder="e.g., NEW, HOT, OFFER">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Badge Color</label>
                            <div class="color-input-group">
                                <input type="color" id="badgeColor" value="#f59e0b">
                                <input type="text" class="form-input" id="badgeColorText" value="#f59e0b">
                            </div>
                        </div>
                    </div>
                    <!-- Status & Visibility -->
                    <div class="form-section">
                        <div class="section-title">👁️ Status & Visibility</div>
                       
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <label class="toggle-switch">
                                <input type="checkbox" id="bannerActive" checked>
                                <span class="toggle-slider"></span>
                            </label>
                            <span style="margin-left: 10px;">Active</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Show on Desktop</label>
                            <label class="toggle-switch">
                                <input type="checkbox" id="showDesktop" checked>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Show on Tablet</label>
                            <label class="toggle-switch">
                                <input type="checkbox" id="showTablet" checked>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Show on Mobile</label>
                            <label class="toggle-switch">
                                <input type="checkbox" id="showMobile" checked>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Priority Order</label>
                            <input type="number" class="form-input" id="priority" value="1" min="1">
                        </div>
                    </div>
                    <!-- Animations -->
                    <div class="form-section">
                        <div class="section-title">✨ Animations</div>
                       
                        <div class="form-group">
                            <label class="form-label">Hover Animation</label>
                            <select class="form-select" id="hoverAnimation">
                                <option value="none">None</option>
                                <option value="fade">Fade</option>
                                <option value="zoom">Zoom</option>
                                <option value="lift">Lift</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Click Animation</label>
                            <select class="form-select" id="clickAnimation">
                                <option value="none">None</option>
                                <option value="scale">Scale</option>
                                <option value="glow">Glow</option>
                                <option value="slide">Slide</option>
                            </select>
                        </div>
                    </div>
                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <button type="button" class="btn btn-primary btn-block" id="createBannerBtn">
                            🎨 Create Banner
                        </button>
                        <button type="button" class="btn btn-secondary btn-block" id="resetFormBtn">
                            🔄 Reset Form
                        </button>
                    </div>
                </form>
            </div>
            <!-- Preview Panel -->
            <div class="preview-panel">
                <div class="preview-controls">
                    <div class="preview-mode">
                        <button class="active" data-mode="desktop">🖥️ Desktop</button>
                        <button data-mode="tablet">📱 Tablet</button>
                        <button data-mode="mobile">📱 Mobile</button>
                    </div>
                   
                    <button class="btn btn-success" id="exportJsonBtn">
                        📥 Export JSON
                    </button>
                   
                    <button class="btn btn-secondary" id="copyJsonBtn">
                        📋 Copy JSON
                    </button>
                   
                    <button class="btn btn-danger" id="clearAllBtn">
                        🗑️ Clear All
                    </button>
                </div>
                <div class="preview-area desktop" id="previewArea">
                    <!-- Top Section -->
                    <div class="banner-section" id="topSection">
                        <div class="section-label">Top Section</div>
                        <div class="banner-grid cols-1" id="topGrid"></div>
                    </div>
                    <!-- Middle Section -->
                    <div class="banner-section" id="middleSection">
                        <div class="section-label">Middle Section</div>
                        <div class="banner-grid cols-1" id="middleGrid"></div>
                    </div>
                    <!-- Bottom Section -->
                    <div class="banner-section" id="bottomSection">
                        <div class="section-label">Bottom Section</div>
                        <div class="banner-grid cols-1" id="bottomGrid"></div>
                    </div>
                </div>
                <!-- JSON Output -->
                <div class="json-output" id="jsonOutput" style="display:none;"></div>
                <!-- Banner Manager -->
                <div class="banner-manager">
                    <h3 style="margin-bottom: 16px;">📋 Created Banners</h3>
                    <div id="bannerList"></div>
                </div>
            </div>
        </div>
    </div>
    <script>
        // Global state
        const API_URL = 'http://localhost/vfs_portal/vfs-admin/api/bannerapi.php'; 
        
        let banners = [];
        let ctaCount = 0;
        let previewMode = 'desktop';
        let draggedElement = null;
        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            initializeEventListeners();
            loadFromLocalStorage();
            updatePreview();
        });
        // Event Listeners
        function initializeEventListeners() {
            // Preview mode buttons
            document.querySelectorAll('.preview-mode button').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    document.querySelectorAll('.preview-mode button').forEach(b => b.classList.remove('active'));
                    e.target.classList.add('active');
                    previewMode = e.target.dataset.mode;
                    updatePreviewMode();
                });
            });
            // Background type change
            document.getElementById('bgType').addEventListener('change', (e) => {
                const type = e.target.value;
                document.getElementById('bgSolidGroup').style.display = type === 'solid' ? 'block' : 'none';
                document.getElementById('bgGradientGroup').style.display = type === 'gradient' ? 'block' : 'none';
                document.getElementById('bgImageGroup').style.display = type === 'image' ? 'block' : 'none';
            });
            // Color inputs sync
            syncColorInputs('bgColor', 'bgColorText');
            syncColorInputs('textColor', 'textColorText');
            syncColorInputs('badgeColor', 'badgeColorText');
            // Slider values
            document.getElementById('borderRadius').addEventListener('input', (e) => {
                document.getElementById('borderRadiusValue').textContent = e.target.value + 'px';
            });
            document.getElementById('opacity').addEventListener('input', (e) => {
                document.getElementById('opacityValue').textContent = Math.round(e.target.value * 100) + '%';
            });
            // Image previews
            setupImagePreview('desktopImage', 'desktopImagePreview');
            setupImagePreview('mobileImage', 'mobileImagePreview');
            // Add CTA button
            document.getElementById('addCtaBtn').addEventListener('click', addCTAField);
            // Create banner
            document.getElementById('createBannerBtn').addEventListener('click', createBanner);
            // Reset form
            document.getElementById('resetFormBtn').addEventListener('click', resetForm);
            // Export JSON
            document.getElementById('exportJsonBtn').addEventListener('click', exportJSON);
            // Copy JSON
            document.getElementById('copyJsonBtn').addEventListener('click', copyJSON);
            // Clear all
            document.getElementById('clearAllBtn').addEventListener('click', clearAllBanners);
        }
        function syncColorInputs(colorId, textId) {
            const colorInput = document.getElementById(colorId);
            const textInput = document.getElementById(textId);
            colorInput.addEventListener('input', (e) => {
                textInput.value = e.target.value;
            });
            textInput.addEventListener('input', (e) => {
                if (/^#[0-9A-F]{6}$/i.test(e.target.value)) {
                    colorInput.value = e.target.value;
                }
            });
        }
        function setupImagePreview(inputId, previewId) {
            document.getElementById(inputId).addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = (event) => {
                        const preview = document.getElementById(previewId);
                        preview.src = event.target.result;
                        preview.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
        function addCTAField() {
            ctaCount++;
            const container = document.getElementById('ctaContainer');
            const ctaItem = document.createElement('div');
            ctaItem.className = 'cta-item';
            ctaItem.dataset.ctaId = ctaCount;
           
            ctaItem.innerHTML = `
                <button type="button" class="cta-remove" onclick="removeCTA(${ctaCount})">×</button>
                <div class="form-group">
                    <label class="form-label">CTA Text</label>
                    <input type="text" class="form-input cta-text" placeholder="Button text">
                </div>
                <div class="form-group">
                    <label class="form-label">CTA URL</label>
                    <input type="text" class="form-input cta-url" placeholder="https://example.com">
                </div>
                <div class="form-group">
                    <label class="form-label">Open in New Tab</label>
                    <label class="toggle-switch">
                        <input type="checkbox" class="cta-newtab">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <div class="form-group">
                    <label class="form-label">Button Color</label>
                    <input type="color" class="cta-color" value="#ffffff">
                </div>
                <div class="form-group">
                    <label class="form-label">Button Background</label>
                    <input type="color" class="cta-bg" value="#1f2937">
                </div>
                <div class="form-group">
                    <label class="form-label">Border Radius (px)</label>
                    <input type="number" class="form-input cta-radius" value="8" min="0">
                </div>
            `;
           
            container.appendChild(ctaItem);
        }
        function removeCTA(id) {
            const item = document.querySelector(`[data-cta-id="${id}"]`);
            if (item) item.remove();
        }
     
        function getCTAs() {
            const ctas = [];
            document.querySelectorAll('.cta-item').forEach(item => {
                ctas.push({
                    text: item.querySelector('.cta-text').value,
                    url: item.querySelector('.cta-url').value,
                    newTab: item.querySelector('.cta-newtab').checked,
                    color: item.querySelector('.cta-color').value,
                    background: item.querySelector('.cta-bg').value,
                    borderRadius: item.querySelector('.cta-radius').value
                });
            });
            return ctas;
        }
        function sortBanners() {
            banners.sort((a, b) => (a.group || '').localeCompare(b.group || '') || a.priority - b.priority || a.section.localeCompare(b.section));
        }
        function updatePreview() {
            const sections = {
                top: document.getElementById('topGrid'),
                middle: document.getElementById('middleGrid'),
                bottom: document.getElementById('bottomGrid')
            };
            // Clear all sections
            Object.values(sections).forEach(section => section.innerHTML = '');
            // Group banners by section
            const groupedBanners = {
                top: [],
                middle: [],
                bottom: []
            };
            banners.forEach(banner => {
                if (banner.section) {
                    groupedBanners[banner.section].push(banner);
                }
            });
            // Render banners
            Object.keys(groupedBanners).forEach(sectionName => {
                const sectionBanners = groupedBanners[sectionName];
                if (sectionBanners.length > 0) {
                    const grid = sections[sectionName];
                    const layout = sectionBanners[0].layout || 'cols-1';
                    grid.className = `banner-grid ${layout}`;
                    sectionBanners.forEach(banner => {
                        const bannerEl = createBannerElement(banner);
                        grid.appendChild(bannerEl);
                    });
                }
            });
        }
        function createBannerElement(banner) {
            const div = document.createElement('div');
            div.className = `banner-item ${banner.status.active ? '' : 'inactive'}`;
            div.dataset.bannerId = banner.id;
            // Background
            let background = '';
            if (banner.background.type === 'solid') {
                background = banner.background.color;
            } else if (banner.background.type === 'gradient') {
                background = `linear-gradient(${banner.background.gradient.angle}deg, ${banner.background.gradient.color1}, ${banner.background.gradient.color2})`;
            }
            // Styling
            const shadow = banner.styling.boxShadow;
            const padding = banner.styling.padding;
            const margin = banner.styling.margin;
            div.style.cssText = `
                background: ${background};
                color: ${banner.textColor};
                border-radius: ${banner.styling.borderRadius}px;
                box-shadow: ${shadow.x}px ${shadow.y}px ${shadow.blur}px ${shadow.spread}px rgba(0,0,0,${shadow.opacity});
                padding: ${padding.top}px ${padding.right}px ${padding.bottom}px ${padding.left}px;
                margin: ${margin.top}px ${margin.right}px ${margin.bottom}px ${margin.left}px;
                z-index: ${banner.styling.zIndex};
                opacity: ${banner.styling.opacity};
            `;
            // Content
            let content = '';
            if (banner.badge.text) {
                content += `<div class="banner-badge" style="background: ${banner.badge.color}; color: white;">${banner.badge.text}</div>`;
            }
            if (banner.title) {
                content += `<div class="banner-title">${banner.title}</div>`;
            }
            if (banner.subtitle) {
                content += `<div class="banner-subtitle">${banner.subtitle}</div>`;
            }
            if (banner.description) {
                content += `<div class="banner-description">${banner.description}</div>`;
            }
            if (banner.ctas.length > 0) {
                content += '<div class="banner-ctas">';
                banner.ctas.forEach(cta => {
                    content += `<a href="${cta.url}" class="banner-cta" ${cta.newTab ? 'target="_blank"' : ''} style="background: ${cta.background}; color: ${cta.color}; border-radius: ${cta.borderRadius}px;">${cta.text}</a>`;
                });
                content += '</div>';
            }
            div.innerHTML = `
                ${banner.desktopImage ? `<img src="${banner.desktopImage}" class="banner-image" alt="${banner.title}">` : ''}
                <div class="banner-content">${content}</div>
            `;
            return div;
        }
        function updateBannerList() {
            const list = document.getElementById('bannerList');
            list.innerHTML = '';
            let currentGroup = '';
            banners.forEach((banner, index) => {
                if (banner.group !== currentGroup) {
                    currentGroup = banner.group;
                    if (currentGroup) {
                        const header = document.createElement('div');
                        header.className = 'group-header';
                        header.textContent = currentGroup;
                        header.style.margin = '16px 0 8px';
                        header.style.fontWeight = 'bold';
                        header.style.color = 'var(--primary)';
                        list.appendChild(header);
                    }
                }
                const item = document.createElement('div');
                item.className = 'banner-list-item';
                item.draggable = true;
                item.dataset.index = index;
                item.innerHTML = `
                    <div class="banner-info">
                        <strong>${banner.group ? banner.group + ' - ' : ''}${banner.title || 'Untitled Banner'}</strong>
                        <div style="font-size: 0.875rem; color: var(--gray);">${banner.section} - ${banner.status.active ? '✅ Active' : '❌ Inactive'}</div>
                    </div>
                    <div class="banner-actions">
                        <button class="icon-btn" onclick="duplicateBanner(${index})" title="Duplicate">📋</button>
                        <button class="icon-btn" onclick="editBanner(${index})" title="Edit">✏️</button>
                        <button class="icon-btn" onclick="deleteBanner(${index})" title="Delete">🗑️</button>
                    </div>
                `;
                item.addEventListener('dragstart', handleDragStart);
                item.addEventListener('dragover', handleDragOver);
                item.addEventListener('drop', handleDrop);
                item.addEventListener('dragend', handleDragEnd);
                list.appendChild(item);
            });
        }
        function handleDragStart(e) {
            draggedElement = e.target;
            e.target.classList.add('dragging');
        }
        function handleDragOver(e) {
            e.preventDefault();
        }
        function handleDrop(e) {
            e.preventDefault();
            if (draggedElement !== e.currentTarget) {
                const fromIndex = parseInt(draggedElement.dataset.index);
                const toIndex = parseInt(e.currentTarget.dataset.index);
               
                const temp = banners[fromIndex];
                banners.splice(fromIndex, 1);
                banners.splice(toIndex, 0, temp);
               
                sortBanners();
                saveToLocalStorage();
                updatePreview();
                updateBannerList();
            }
        }
        function handleDragEnd(e) {
            e.target.classList.remove('dragging');
        }
        function duplicateBanner(index) {
            const banner = JSON.parse(JSON.stringify(banners[index]));
            banner.id = Date.now();
            banner.title = banner.title + ' (Copy)';
            banners.push(banner);
            sortBanners();
            saveToLocalStorage();
            updatePreview();
            updateBannerList();
        }
        async function editBanner(index) {
    const banner = banners[index];
    
    // Populate form with banner data
    document.getElementById('groupName').value = banner.group || '';
    document.getElementById('bannerTitle').value = banner.title || '';
    document.getElementById('bannerSubtitle').value = banner.subtitle || '';
    document.getElementById('bannerDescription').value = banner.description || '';
    document.getElementById('bannerSection').value = banner.section || 'top';
    document.getElementById('bannerLayout').value = banner.layout || 'cols-1';
    
    // Set images
    if (banner.desktopImage) {
        document.getElementById('desktopImagePreview').src = banner.desktopImage;
        document.getElementById('desktopImagePreview').style.display = 'block';
    }
    if (banner.mobileImage) {
        document.getElementById('mobileImagePreview').src = banner.mobileImage;
        document.getElementById('mobileImagePreview').style.display = 'block';
    }
    
    // Set background
    document.getElementById('bgType').value = banner.background.type || 'solid';
    document.getElementById('bgType').dispatchEvent(new Event('change'));
    document.getElementById('bgColor').value = banner.background.color || '#6366f1';
    document.getElementById('bgColorText').value = banner.background.color || '#6366f1';
    
    if (banner.background.gradient) {
        document.getElementById('gradientColor1').value = banner.background.gradient.color1 || '#6366f1';
        document.getElementById('gradientColor2').value = banner.background.gradient.color2 || '#8b5cf6';
        document.getElementById('gradientAngle').value = banner.background.gradient.angle || 135;
    }
    
    // Set text color
    document.getElementById('textColor').value = banner.textColor || '#ffffff';
    document.getElementById('textColorText').value = banner.textColor || '#ffffff';
    
    // Set CTAs
    document.getElementById('ctaContainer').innerHTML = '';
    if (banner.ctas && banner.ctas.length > 0) {
        banner.ctas.forEach(cta => {
            addCTAField();
            const ctaItems = document.querySelectorAll('.cta-item');
            const lastCta = ctaItems[ctaItems.length - 1];
            
            lastCta.querySelector('.cta-text').value = cta.text || '';
            lastCta.querySelector('.cta-url').value = cta.url || '';
            lastCta.querySelector('.cta-newtab').checked = cta.newTab || false;
            lastCta.querySelector('.cta-color').value = cta.color || '#ffffff';
            lastCta.querySelector('.cta-bg').value = cta.background || '#1f2937';
            lastCta.querySelector('.cta-radius').value = cta.borderRadius || 8;
        });
    }
    
    // Set styling
    if (banner.styling) {
        document.getElementById('borderRadius').value = banner.styling.borderRadius || 12;
        document.getElementById('borderRadiusValue').textContent = (banner.styling.borderRadius || 12) + 'px';
        
        if (banner.styling.boxShadow) {
            document.getElementById('shadowX').value = banner.styling.boxShadow.x || 0;
            document.getElementById('shadowY').value = banner.styling.boxShadow.y || 4;
            document.getElementById('shadowBlur').value = banner.styling.boxShadow.blur || 12;
            document.getElementById('shadowSpread').value = banner.styling.boxShadow.spread || 0;
            document.getElementById('shadowColor').value = banner.styling.boxShadow.color || '#000000';
            document.getElementById('shadowOpacity').value = banner.styling.boxShadow.opacity || 0.1;
        }
        
        if (banner.styling.padding) {
            document.getElementById('paddingTop').value = banner.styling.padding.top || 24;
            document.getElementById('paddingRight').value = banner.styling.padding.right || 24;
            document.getElementById('paddingBottom').value = banner.styling.padding.bottom || 24;
            document.getElementById('paddingLeft').value = banner.styling.padding.left || 24;
        }
        
        if (banner.styling.margin) {
            document.getElementById('marginTop').value = banner.styling.margin.top || 0;
            document.getElementById('marginRight').value = banner.styling.margin.right || 0;
            document.getElementById('marginBottom').value = banner.styling.margin.bottom || 0;
            document.getElementById('marginLeft').value = banner.styling.margin.left || 0;
        }
        
        document.getElementById('zIndex').value = banner.styling.zIndex || 1;
        document.getElementById('opacity').value = banner.styling.opacity || 1;
        document.getElementById('opacityValue').textContent = Math.round((banner.styling.opacity || 1) * 100) + '%';
    }
    
    // Set badge
    if (banner.badge) {
        document.getElementById('badgeText').value = banner.badge.text || '';
        document.getElementById('badgeColor').value = banner.badge.color || '#f59e0b';
        document.getElementById('badgeColorText').value = banner.badge.color || '#f59e0b';
    }
    
    // Set status
    if (banner.status) {
        document.getElementById('bannerActive').checked = banner.status.active !== false;
        document.getElementById('showDesktop').checked = banner.status.showDesktop !== false;
        document.getElementById('showTablet').checked = banner.status.showTablet !== false;
        document.getElementById('showMobile').checked = banner.status.showMobile !== false;
        document.getElementById('priority').value = banner.status.priority || 1;
    }
    
    // Set animations
    if (banner.animations) {
        document.getElementById('hoverAnimation').value = banner.animations.hover || 'none';
        document.getElementById('clickAnimation').value = banner.animations.click || 'none';
    }
    
    // Change button to Update mode
    const createBtn = document.getElementById('createBannerBtn');
    createBtn.textContent = '✏️ Update Banner';
    createBtn.onclick = async function() {
        // Get updated data
        const updatedBanner = {
           id: banner.id,
            group: document.getElementById('groupName').value,
            title: document.getElementById('bannerTitle').value,
            subtitle: document.getElementById('bannerSubtitle').value,
            description: document.getElementById('bannerDescription').value,
            section: document.getElementById('bannerSection').value,
            layout: document.getElementById('bannerLayout').value,
            desktopImage: document.getElementById('desktopImagePreview').src || '',
            mobileImage: document.getElementById('mobileImagePreview').src || '',
            background: {
                type: document.getElementById('bgType').value,
                color: document.getElementById('bgColor').value,
                gradient: {
                    color1: document.getElementById('gradientColor1').value,
                    color2: document.getElementById('gradientColor2').value,
                    angle: document.getElementById('gradientAngle').value
                }
            },
            textColor: document.getElementById('textColor').value,
            ctas: getCTAs(),
            styling: {
                borderRadius: document.getElementById('borderRadius').value,
                boxShadow: {
                    x: document.getElementById('shadowX').value,
                    y: document.getElementById('shadowY').value,
                    blur: document.getElementById('shadowBlur').value,
                    spread: document.getElementById('shadowSpread').value,
                    color: document.getElementById('shadowColor').value,
                    opacity: document.getElementById('shadowOpacity').value
                },
                padding: {
                    top: document.getElementById('paddingTop').value,
                    right: document.getElementById('paddingRight').value,
                    bottom: document.getElementById('paddingBottom').value,
                    left: document.getElementById('paddingLeft').value
                },
                margin: {
                    top: document.getElementById('marginTop').value,
                    right: document.getElementById('marginRight').value,
                    bottom: document.getElementById('marginBottom').value,
                    left: document.getElementById('marginLeft').value
                },
                zIndex: document.getElementById('zIndex').value,
                opacity: document.getElementById('opacity').value
            },
            badge: {
                text: document.getElementById('badgeText').value,
                color: document.getElementById('badgeColor').value
            },
            status: {
                active: document.getElementById('bannerActive').checked,
                showDesktop: document.getElementById('showDesktop').checked,
                showTablet: document.getElementById('showTablet').checked,
                showMobile: document.getElementById('showMobile').checked,
                priority: document.getElementById('priority').value
            },
            animations: {
                hover: document.getElementById('hoverAnimation').value,
                click: document.getElementById('clickAnimation').value
            }
        };
        
        // Update in array
        banners[index] = updatedBanner;
        
        // Update on server
        await updateBannerOnServer(banner.id, updatedBanner);
        
        sortBanners();
        saveToLocalStorage();
        updatePreview();
        updateBannerList();
        resetForm();
        
        // Reset button
        createBtn.textContent = '🎨 Create Banner';
        createBtn.onclick = createBanner;
        
        alert('Banner updated successfully!');
    };
    
    // Scroll to top of form
    document.querySelector('.form-panel').scrollTo({ top: 0, behavior: 'smooth' });
}

        async function deleteBanner(index) {
    if (confirm('Are you sure you want to delete this banner?')) {
        const banner = banners[index];
        
        // Delete from server
        await deleteBannerOnServer(banner.id);
        
        // Remove from local array
        banners.splice(index, 1);
        
        sortBanners();
        saveToLocalStorage();
        updatePreview();
        updateBannerList();
    }
}
        function resetForm() {
            document.getElementById('bannerForm').reset();
            document.getElementById('ctaContainer').innerHTML = '';
            document.getElementById('desktopImagePreview').style.display = 'none';
            document.getElementById('mobileImagePreview').style.display = 'none';
            ctaCount = 0;
        }
        function updatePreviewMode() {
            const previewArea = document.getElementById('previewArea');
            previewArea.className = `preview-area ${previewMode}`;
        }
        function exportJSON() {
            const jsonOutput = document.getElementById('jsonOutput');
            const groups = {};
            banners.forEach(b => {
                const g = b.group || 'ungrouped';
                if (!groups[g]) groups[g] = [];
                groups[g].push(b);
            });
            const formattedJSON = JSON.stringify({ groups }, null, 2);
            jsonOutput.textContent = formattedJSON;
            jsonOutput.style.display = 'block';
            const blob = new Blob([formattedJSON], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'banners-export.json';
            a.click();
            URL.revokeObjectURL(url);
        }
        function copyJSON() {
            const jsonOutput = document.getElementById('jsonOutput');
            const groups = {};
            banners.forEach(b => {
                const g = b.group || 'ungrouped';
                if (!groups[g]) groups[g] = [];
                groups[g].push(b);
            });
            const formattedJSON = JSON.stringify({ groups }, null, 2);
            jsonOutput.textContent = formattedJSON;
            jsonOutput.style.display = 'block';
            navigator.clipboard.writeText(formattedJSON).then(() => {
                alert('JSON copied to clipboard!');
            });
        }
        async function clearAllBanners() {
    if (confirm('Are you sure you want to clear all banners? This action cannot be undone.')) {
        // Clear on server
        await clearAllBannersOnServer();
        
        // Clear local array
        banners = [];
        
        saveToLocalStorage();
        updatePreview();
        updateBannerList();
        
        alert('All banners cleared successfully!');
    }
}
function saveToLocalStorage() {
    localStorage.setItem("bannerSystemData", JSON.stringify({ banners }));
}


        function loadFromLocalStorage() {
        loadBannersFromServer();
       }

       async function loadBannersFromServer() {
    try {
        const response = await fetch(`${API_URL}?action=getAllBanners`);
        const result = await response.json();
        
        if (result.success) {
            banners = result.data || [];
            sortBanners();
            updatePreview();
            updateBannerList();
        } else {
            console.error('Failed to load banners:', result.message);
        }
    } catch (error) {
        console.error('Error loading banners:', error);
        // Fallback to localStorage if API fails
        try {
            const data = localStorage.getItem('bannerSystemData');
            if (data) {
                const parsed = JSON.parse(data);
                banners = parsed.banners || [];
                sortBanners();
                updatePreview();
                updateBannerList();
            }
        } catch (e) {
            console.error('Fallback load error:', e);
        }
    }
}
async function createBanner() {
    const banner = {
        group: document.getElementById('groupName').value,
        title: document.getElementById('bannerTitle').value,
        subtitle: document.getElementById('bannerSubtitle').value,
        description: document.getElementById('bannerDescription').value,
        section: document.getElementById('bannerSection').value,
        layout: document.getElementById('bannerLayout').value,
        desktopImage: document.getElementById('desktopImagePreview').src || '',
        mobileImage: document.getElementById('mobileImagePreview').src || '',
        background: {
            type: document.getElementById('bgType').value,
            color: document.getElementById('bgColor').value,
            gradient: {
                color1: document.getElementById('gradientColor1').value,
                color2: document.getElementById('gradientColor2').value,
                angle: document.getElementById('gradientAngle').value
            }
        },
        textColor: document.getElementById('textColor').value,
        ctas: getCTAs(),
        styling: {
            borderRadius: document.getElementById('borderRadius').value,
            boxShadow: {
                x: document.getElementById('shadowX').value,
                y: document.getElementById('shadowY').value,
                blur: document.getElementById('shadowBlur').value,
                spread: document.getElementById('shadowSpread').value,
                color: document.getElementById('shadowColor').value,
                opacity: document.getElementById('shadowOpacity').value
            },
            padding: {
                top: document.getElementById('paddingTop').value,
                right: document.getElementById('paddingRight').value,
                bottom: document.getElementById('paddingBottom').value,
                left: document.getElementById('paddingLeft').value
            },
            margin: {
                top: document.getElementById('marginTop').value,
                right: document.getElementById('marginRight').value,
                bottom: document.getElementById('marginBottom').value,
                left: document.getElementById('marginLeft').value
            },
            zIndex: document.getElementById('zIndex').value,
            opacity: document.getElementById('opacity').value
        },
        badge: {
            text: document.getElementById('badgeText').value,
            color: document.getElementById('badgeColor').value
        },
        status: {
            active: document.getElementById('bannerActive').checked,
            showDesktop: document.getElementById('showDesktop').checked,
            showTablet: document.getElementById('showTablet').checked,
            showMobile: document.getElementById('showMobile').checked,
            priority: document.getElementById('priority').value
        },
        animations: {
            hover: document.getElementById('hoverAnimation').value,
            click: document.getElementById('clickAnimation').value
        }
    };

    try {
        const response = await fetch(`${API_URL}?action=createBanner`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(banner)
        });

        const text = await response.text();
console.log("RAW API RESPONSE:", text);

let result;
try {
    result = JSON.parse(text);
} catch (e) {
    alert("❌ Server returned invalid JSON. Check PHP error in Network tab.");
    return;
}


        if (!result.success) {
            alert("DB INSERT FAILED: " + result.message);
            return;
        }

        banner.id = result.id; // get inserted ID from server
        banners.push(banner);

        sortBanners();
        updatePreview();
        updateBannerList();
        resetForm();

        alert("✅ Banner saved to database successfully!");
    } catch (err) {
        console.error(err);
        alert("❌ Server not reachable. Banner NOT saved.");
    }
}


async function updateBannerOnServer(bannerId, bannerData) {
    try {
        const response = await fetch(`${API_URL}?action=updateBanner&id=${bannerId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(bannerData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            console.log('Banner updated successfully');
            return true;
        } else {
            console.error('Failed to update banner:', result.message);
            return false;
        }
    } catch (error) {
        console.error('Error updating banner:', error);
        return false;
    }
}

async function deleteBannerOnServer(bannerId) {
    try {
        const response = await fetch(`${API_URL}?action=deleteBanner&id=${bannerId}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (result.success) {
            console.log('Banner deleted successfully');
            return true;
        } else {
            console.error('Failed to delete banner:', result.message);
            return false;
        }
    } catch (error) {
        console.error('Error deleting banner:', error);
        return false;
    }
}

async function syncBannersToServer() {
    return; // disabled (this was crashing your API)
}

async function clearAllBannersOnServer() {
    try {
        const response = await fetch(`${API_URL}?action=deleteAll`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (result.success) {
            console.log('All banners cleared successfully');
            return true;
        } else {
            console.error('Failed to clear banners:', result.message);
            return false;
        }
    } catch (error) {
        console.error('Error clearing banners:', error);
        return false;
    }
}

        // Add some example banners on first load
        
    </script>
</body>
</html>