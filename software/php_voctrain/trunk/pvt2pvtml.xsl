<?xml version="1.0" encoding="ISO-8859-1" ?>
<!--
$Id: pvt2pvtml.xsl,v 1.1 2002/03/07 09:17:18 mrfrost Exp $

 This stylesheet converts old pvt documents, used by php_voctrain 0.1.0,
 into the new pvtml documents. pvtml is (restricted) compatible to kvtml.
-->
<xsl:stylesheet
 xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
 version="1.0">

    <xsl:output
        method="xml"
        indent="yes"
        media-type="text/xml"
        encoding="ISO-8859-15"
        doctype-system="voctrain.dtd" />

    <xsl:template match="pvt">
    <pvtml title="converted pvt document" author="Mr. X">

    <languages>
    <xsl:for-each select="language">
    <o><xsl:value-of select="@first" /></o>
    <t><xsl:value-of select="@second" /></t>
    </xsl:for-each>
    </languages>

    <lesson>
    <xsl:for-each select="chapter">
        <xsl:element name="desc">
        <xsl:attribute name="no"><xsl:value-of select="position()" /></xsl:attribute>
            <xsl:value-of select="@name" />
        </xsl:element>
    </xsl:for-each>
    </lesson>

    <xsl:for-each select="chapter">
        <xsl:variable name="cp"><xsl:value-of select="position()" /></xsl:variable>
        <xsl:for-each select="voc">
            <xsl:element name="e">
            <xsl:attribute name="m">
            <xsl:value-of select="$cp" />
            </xsl:attribute>
            <o><xsl:value-of select="@first" /></o>
            <t><xsl:value-of select="@second" /></t>
            </xsl:element>
            <xsl:text>
            </xsl:text>
        </xsl:for-each>
    </xsl:for-each>

    </pvtml>
    </xsl:template>

</xsl:stylesheet>
