 AI Auto Content Generator (AITF)
Autonomous SEO Content Engine & Competitor Intelligence Pipeline
AI Auto Content Generator is an enterprise-grade autonomous engine designed to handle the entire content lifecycle—from competitor discovery to SEO-optimized publishing. Built for Md Moaj's "TechForUs" platform, it transforms a WordPress site into a self-growing authority hub.
🧠 The "Autonomous" Workflow
Unlike standard AI writers, AITF operates on a Discovery-to-Publication loop:
Monitor: Scans competitor REST APIs for new high-performing posts.
Analyze: Performs deep semantic analysis, extracting H2/H3 structures and core facts.
Deduplicate: Uses SHA-256 hashing to ensure 100% unique topic coverage.
Synthesize: Orchestrates LLMs (Groq/OpenAI) via a custom Factory Pattern to rewrite content with E-E-A-T standards.
Visualize: Automatically generates a branded featured image with gradient overlays and centered typography.
Index: Staggers publication and pings Google/Bing Sitemaps for near-instant indexing.
✨ Key Features
📡 Competitor Intelligence
REST API Integration: Validates and monitors remote WordPress sites seamlessly.
Deep Content Extraction: Pulls full HTML content and meta-data for superior AI context.
✍️ Intelligent AI Orchestration
Contextual Mode Switching: Automatically detects if a topic should be a How-To Guide (Action-oriented) or a News Report (Inverted Pyramid style).
Multi-Model Support: Native integration for Groq (GPT-OSS 120B) and OpenAI (GPT-4o, GPT-4 Turbo).
JSON Resilience: Features aggressive cleaning logic to handle common LLM formatting "hallucinations".
🖼️ Automated Visual Branding
Stock Photo Integration: Dynamic searching via Pixabay and Pexels.
Branded Overlays: Uses the GD library to apply professional gradient overlays and Roboto-Bold typography.
Smart Layout: Enforces horizontally centered text with black drop-shadows for maximum readability on any device.
⚙️ Admin & SEO Controls
Fixed Taxonomy Guardrail: Limits AI to a user-defined list of 10 categories to maintain site structure.
Audit Logging: Comprehensive event tracking with CSV export for token usage and cost analysis.
SEO Sync: Direct compatibility with Yoast SEO and RankMath metadata.
🛠️ Technical Stack
Backend: PHP 7.4+.
Architecture: PSR-4 Autoloading & Abstract Factory Design Pattern.
Database: Custom MySQL tables for Logs, Competitors, and Content Hashes.
Automation: WP-Cron with self-healing scheduling logic.
Frontend: AJAX-driven admin interfaces (jQuery).
🚀 Installation & Setup
Clone: git clone https://github.com/your-username/ai-auto-content-generator.git
Activate: Upload to /wp-content/plugins/ and activate via the WordPress Dashboard.
Configure: Navigate to AI Content Gen > Settings to add your API keys.
Target: Add competitor URLs in the Competitors tab.
Scale: Let the 4-hour cron handle the rest.
👤 Author
Md Moaj Software Engineer | Python & Laravel Expert LinkedIn | YouTube - sondhanYT
