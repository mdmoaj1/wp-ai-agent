# AI Auto Content Generator (AITF)
### *Autonomous SEO Content Engine & Competitor Intelligence Pipeline*

[![WordPress Version](https://img.shields.io/badge/WordPress-6.0+-21759b.svg)](https://wordpress.org/)
[![PHP Version](https://img.shields.io/badge/PHP-7.4+-777bb4.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL--2.0-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![AI Providers](https://img.shields.io/badge/AI-Groq%20%7C%20OpenAI-blueviolet)](https://groq.com)

**AI Auto Content Generator** is an enterprise-grade autonomous engine designed to handle the entire content lifecycle—from competitor discovery to SEO-optimized publishing. Built with a modular, PSR-4 compliant architecture, it transforms a WordPress site into a self-growing authority hub.

---

## 🧠 The "Autonomous" Workflow
AITF operates on a continuous **Discovery-to-Publication** loop, managed by a self-healing cron system:

1.  **Monitor:** Scans competitor REST APIs for new high-performing posts.
2.  **Analyze:** Performs deep semantic analysis, extracting H2/H3 structures and core facts.
3.  **Deduplicate:** Employs SHA-256 hashing to ensure 100% unique topic coverage.
4.  **Synthesize:** Orchestrates LLMs (Groq/OpenAI) via a custom Factory Pattern to rewrite content with E-E-A-T standards.
5.  **Visualize:** Automatically generates branded featured images with gradient overlays and centered typography.
6.  **Index:** Staggers publication and pings Google/Bing Sitemaps for near-instant indexing.

---

## ✨ Key Features

### 📡 Competitor Intelligence
* **REST API Integration:** Validates and monitors remote WordPress sites seamlessly.
* **Deep Content Extraction:** Pulls full HTML content and meta-data for superior AI context.

### ✍️ Intelligent AI Orchestration
* **Contextual Mode Switching:** Automatically detects if a topic should be a **How-To Guide** (Action-oriented) or a **News Report** (Inverted Pyramid style).
* **Multi-Model Support:** Native integration for **Groq** (GPT-OSS 120B) and **OpenAI** (GPT-4o, GPT-4 Turbo).
* **JSON Resilience:** Features aggressive cleaning logic to handle common LLM formatting inconsistencies.

### 🖼️ Automated Visual Branding
* **Stock Photo Integration:** Dynamic searching via **Pixabay** and **Pexels**.
* **Branded Overlays:** Uses the GD library to apply professional gradient overlays and Roboto-Bold typography.
* **Smart Layout:** Enforces horizontally centered text with black drop-shadows for maximum readability.

### ⚙️ Admin & SEO Controls
* **Fixed Taxonomy Guardrail:** Limits AI to a user-defined list of 10 categories to maintain site structure.
* **Audit Logging:** Comprehensive event tracking with CSV export for token usage and cost analysis.
* **SEO Sync:** Direct compatibility with **Yoast SEO** and **RankMath** metadata.

---

## 🛠️ Technical Stack
* **Backend:** PHP 7.4+.
* **Architecture:** PSR-4 Autoloading & Abstract Factory Design Pattern.
* **Database:** Custom MySQL tables for Logs, Competitors, and Content Hashes.
* **Automation:** WP-Cron with self-healing scheduling logic.
* **Frontend:** AJAX-driven admin interfaces (jQuery).

---

## 🚀 Installation & Setup

1.  **Clone:** `git clone https://github.com/techforus/ai-auto-content-generator.git`
2.  **Activate:** Upload to `/wp-content/plugins/` and activate via the WordPress Dashboard.
3.  **Configure:** Navigate to **AI Content Gen > Settings** to add your API keys.
4.  **Target:** Add competitor URLs in the **Competitors** tab.
5.  **Scale:** Let the 4-hour cron handle the rest.

---

## 👤 Author
**Md Moaj** *Software Engineer | Python & Laravel Expert* [LinkedIn](https://linkedin.com/in/mdmoaj) | [YouTube - sondhanYT](https://youtube.com/sondhanYT)

---
*Developed for the TechForUs SEO Content Engine.*
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
