<p><strong>dc_gallery</strong> is a modern and highly configurable Joomla content plugin designed to render image galleries directly inside articles using a simple shortcode.</p>
<p>The plugin focuses on flexibility, performance, and clean architecture. It allows developers and content creators to easily display images in multiple layouts without writing custom JavaScript or CSS.</p>
<h3>✨ Key Features</h3>
<ul>
<li><strong>Shortcode-based rendering</strong>
<ul>
<li>Use <code>{dcgallery source="folder"}</code> directly inside Joomla articles</li>
<li>Automatically replaces shortcode with generated HTML and JS</li>
</ul>
</li>
<li><strong>Multiple display modes</strong>
<ul>
<li><code>normal</code> &ndash; CSS Grid with fixed aspect ratio</li>
<li><code>tiles</code> &ndash; Masonry layout powered by Macy.js</li>
<li><code>slideshow</code> &ndash; Single-slide Swiper</li>
<li><code>carousel</code> &ndash; Multi-slide Swiper</li>
<li><code>coverflow</code> &ndash; 3D Swiper effect</li>
<li><code>cards</code> &ndash; Card-style Swiper animation</li>
<li><code>thumbs</code> &ndash; Slider with synchronized thumbnails</li>
</ul>
</li>
<li><strong>Smart asset loading</strong>
<ul>
<li>Loads only required libraries depending on selected mode</li>
<li>Always includes:
<ul>
<li>GLightbox (lightbox functionality)</li>
<li>Core plugin CSS</li>
</ul>
</li>
<li>Conditionally loads:
<ul>
<li>Swiper (for slider modes)</li>
<li>Macy.js (for masonry layout)</li>
</ul>
</li>
</ul>
</li>
<li><strong>Image handling</strong>
<ul>
<li>Reads images from a defined folder</li>
<li>Supports: <code>jpg</code>, <code>jpeg</code>, <code>png</code>, <code>gif</code>, <code>webp</code>, <code>avif</code></li>
<li>Automatically sorts images alphabetically</li>
<li>Uses filename as image title</li>
</ul>
</li>
<li><strong>Advanced configuration</strong>
<ul>
<li>Global settings + shortcode overrides</li>
<li>Control:
<ul>
<li>columns, spacing, ratios</li>
<li>slides visibility</li>
<li>loop behavior (Swiper &amp; GLightbox)</li>
<li>navigation styling (colors, opacity)</li>
</ul>
</li>
</ul>
</li>
<li><strong>Custom navigation UI</strong>
<ul>
<li>Accessible (ARIA, keyboard support)</li>
<li>Fully customizable appearance</li>
<li>Smooth hover transitions</li>
</ul>
</li>
<li><strong>Data validation &amp; normalization</strong>
<ul>
<li>Safe parsing of all parameters</li>
<li>Fallbacks for invalid values</li>
<li>Range limits and format validation</li>
</ul>
</li>
<li><strong>Internationalization (i18n)</strong>
<ul>
<li>Built-in support for English and Polish</li>
<li>Translation keys for UI and errors</li>
</ul>
</li>
<li><strong>Error handling</strong>
<ul>
<li>Graceful fallback via HTML comments</li>
<li>Debug-friendly output for missing data or folders</li>
</ul>
</li>
</ul>
<h3>🚀 Example Usage</h3>

<div>{dcgallery source="portfolio" mode="carousel" slides_visible="4" slide_ratio="16:9"}</div>

<h3>🎯 Use Cases</h3>
<ul>
<li>Portfolio galleries</li>
<li>Product showcases</li>
<li>Blog image collections</li>
<li>Interactive sliders</li>
<li>Visual storytelling sections</li>
</ul>

<h2>🌐 Official project page</h2>
<p><a href="https://www.designcart.pl/laboratorium/306-galeria-obrazow-dla-joomla-z-wieloma-trybami-wyswietlania-darmowy-plugin.html">https://www.designcart.pl/laboratorium/306-galeria-obrazow-dla-joomla-z-wieloma-trybami-wyswietlania-darmowy-plugin.html</a></p>
