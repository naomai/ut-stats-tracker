<?xml version="1.0" encoding="UTF-8" ?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
    <xsl:output method="html" />
    <xsl:template match="playerdata">
        <html>
            <head>
                <title>Player <xsl:value-of select="./players/name" /></title>
            </head>
            <body>
                <h1><xsl:value-of select="./players/name" /></h1>
                <h2>Basic info</h2>
                <xsl:for-each select="./players/*">
                    <p>
                        <em><xsl:value-of select="name(.)"/></em>: 
                        <xsl:value-of select="."/>
                    </p>
                </xsl:for-each>
                <xsl:apply-templates select="player_logs|player_stats" />
            </body>
        </html>
    </xsl:template>

    <xsl:template match="player_logs">
        <h2>Logs</h2>
        <xsl:call-template name="dump_table"></xsl:call-template>
    </xsl:template>

    <xsl:template match="player_stats">
        <h2>Stats</h2>
        <xsl:call-template name="dump_table"></xsl:call-template>
    </xsl:template>

    <xsl:template name="dump_table">

        <table>
            <thead>
                <xsl:for-each select="./header/column">
                    <th><xsl:value-of select="."/></th>
                </xsl:for-each>
            </thead>
            <tbody>
                <xsl:for-each select="./record">
                    <tr>
                        <xsl:for-each select="./*">
                            <td>
                                <xsl:value-of select="."/>
                            </td>
                        </xsl:for-each>
                    </tr>
                </xsl:for-each>
            </tbody>
        </table>
    </xsl:template>
</xsl:stylesheet>
