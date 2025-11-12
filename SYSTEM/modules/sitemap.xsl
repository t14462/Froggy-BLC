<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0"
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
  xmlns:sitemap="http://www.sitemaps.org/schemas/sitemap/0.9"
  exclude-result-prefixes="sitemap">

  <xsl:output method="html" encoding="UTF-8" indent="yes"/>

  <xsl:template match="/">
    <html>
      <head>
        <title>Sitemap Viewer</title>
        <style>
          body {
            font-family: sans-serif;
            background: #f8f8f8;
            padding: 2em;
            color: #333;
          }
          h1 {
            font-size: 24px;
            margin-bottom: 1em;
          }
          table {
            border-collapse: collapse;
            width: 100%;
            background: white;
            box-shadow: 0 0 8px rgba(0,0,0,0.1);
          }
          th, td {
            text-align: left;
            padding: 8px 12px;
            border-bottom: 1px solid #ddd;
          }
          th {
            background-color: #f0f0f0;
          }
          a {
            color: #0066cc;
            text-decoration: none;
            word-break: break-all;
          }
          a:hover {
            text-decoration: underline;
          }
        </style>
      </head>
      <body>
        <h1>XML Sitemap Viewer</h1>
        <table>
          <tr>
            <th>URL</th>
            <th>Last Modified</th>
          </tr>
          <xsl:for-each select="//sitemap:url">
            <tr>
              <td><a href="{sitemap:loc}"><xsl:value-of select="sitemap:loc"/></a></td>
              <td><xsl:value-of select="sitemap:lastmod"/></td>
            </tr>
          </xsl:for-each>
        </table>
      </body>
    </html>
  </xsl:template>
</xsl:stylesheet>
