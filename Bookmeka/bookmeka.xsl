<?xml version="1.0" encoding="UTF-8"?>
<!--

LGPL  http://www.gnu.org/licenses/lgpl.html
© 2015 Frederic.Glorieux@fictif.org 

Part of Bookmeka, an Omeka plugin for books (tei, odt, epub)

This XSLT1 pilot allow you to handle your own tags, or your linking policy in some case.


The root template (@match="/") will conduct the imported templates.
It’s not a good idea to change it.



-->
<xsl:transform version="1.1" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns="http://www.w3.org/1999/xhtml" xmlns:tei="http://www.tei-c.org/ns/1.0" exclude-result-prefixes="tei">
  <!-- Import of html transformation chain -->
  <xsl:import href="libraries/Transtei/tei2site.xsl"/>
  <!-- Import of Dublin Core generator -->
  <xsl:import href="libraries/Transtei/tei2dc.xsl"/>
  <!-- Global constants for known modes  -->
  <xsl:variable name="site">site</xsl:variable>
  <xsl:variable name="dc">dc</xsl:variable>  
  <!-- Mode, set by the caller, especially to choose between multi-page generation (mode="site") or monopage (default) -->
  <xsl:param name="mode"/>
  <!-- Do not touch or will break Bookmeka logic -->
  <xsl:template match="/">
    <xsl:choose>
      <xsl:when test="$mode = $site">
        <xsl:apply-templates select="." mode="site"/>
      </xsl:when>
      <xsl:when test="$mode = $dc">
        <xsl:apply-templates select="/*/tei:teiHeader" mode="dc"/>
      </xsl:when>
      <xsl:otherwise>
        <xsl:apply-templates select="." mode="html"/>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>
  <!-- Example of link rewrite to point an external resource -->
  <xsl:template match="tei:rs/@ref">
    <xsl:attribute name="href"><xsl:value-of select="."/></xsl:attribute>
  </xsl:template>
  <!-- Example of other properties to add to default Dublin Core record, current node is /*/tei:teiHeader -->
  <xsl:template name="mydc">
    <!--
      <dc:publisher>My organisation</dc:publisher>
      -->
  </xsl:template>
</xsl:transform>
