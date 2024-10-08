﻿Imports System.Net
Imports utt_updater3.SocketMaster
Imports System.Threading
Imports MySql.Data.MySqlClient
Imports System.Text
Imports System.Text.Encoding


Public Class ServerScanner
    Implements IDisposable

    Public scanInterval = 120
    Public scanStart As Date
    Public scanEnd As Date
    Friend scanLastActivity, scanLastTouchAll As Date
    Public masterServerLastUpdate As Date
    Public masterServerUpdateInterval As Integer = 3600
    Public masterServerLastPing As Date
    Public masterServerPingInterval As Integer = 600

    Public serversCountTotal As Integer
    Public serversCountOnline As Integer

    Protected Friend log As DeVlog
    Protected Friend ini As N14INI
    Protected Friend db As MySQLDB
    Protected Friend dyncfg As DynConfig

    Protected WithEvents masterServerQuery As MasterServerManager
    Protected WithEvents socketMaster As SocketMaster
    Protected targets As New Hashtable
    Protected targetsCollectionLock As New Object 'prevent 'For Each mess when collection is modified'

    Protected tickCounter As Integer = 0
    Private transaction As MySqlTransaction


    Event OnScanBegin(serverCount As Integer)
    Event OnScanComplete(scannedServerCount As Integer, onlineServerCount As Integer, elapsedTime As TimeSpan)

    Dim fragmentedPackets As New Hashtable
    Dim disposed As Boolean = False

    Public Sub New(scannerConfig As ServerScannerConfig)
        With scannerConfig
            scanInterval = .scanInterval
            masterServerUpdateInterval = .masterServerUpdateInterval
            log = .log
            db = .db
            dyncfg = New DynConfig(db.dbh)
            dyncfg.setProperty("net.reaper.configsrc", .iniFile, True)
            masterServerQuery = .masterServerManager
        End With

        prepareDB()

        debugWriteLine("ServerScanner ready")

    End Sub

    Public Sub performScan()
        Dim serversToScan As List(Of String), recentServersTimeRange = 3600, includeAncient As Boolean = False

        If (Date.UtcNow - masterServerLastUpdate).TotalSeconds > masterServerUpdateInterval Then ' full scan
            masterServerQuery.refreshServerList()
            masterServerLastUpdate = Date.UtcNow
            masterServerLastPing = Date.UtcNow
            recentServersTimeRange = 86400 * 30
            includeAncient = True
        End If

        If masterServerPingInterval > 0 AndAlso (Date.UtcNow - masterServerLastPing).TotalSeconds > masterServerPingInterval Then ' monitors the other master servers
            masterServerQuery.pingMasterServers()
            masterServerLastPing = Date.UtcNow
        End If

        serversToScan = masterServerQuery.getList()
        Dim serversFromDB = getRecentlyScannedServerList(recentServersTimeRange, includeAncient)
        For Each server As String In serversFromDB
            serversToScan.Add(server)
        Next
        serversFromDB = getServersPendingQueue()
        For Each server As String In serversFromDB
            serversToScan.Add(server)
        Next

        debugWriteLine("Scanning using settings: recentServersTimeRange={0},includeAncient={1}", recentServersTimeRange, includeAncient)

        serversToScan = serversToScan.Distinct().ToList

        RaiseEvent OnScanBegin(serversToScan.Count)

        initSockets()
        initTargets(serversToScan)

        scanStart = Date.UtcNow
        scanLastActivity = Date.UtcNow
        serversCountTotal = targets.Count

        touchAll(True)

        tickCounter = 0

        Do While ((Date.UtcNow - scanLastActivity).TotalSeconds < 10 Or (Date.UtcNow - scanLastTouchAll).TotalSeconds >= 3) ' second check: avoid ending the scan too early when 'time-travelling'
            socketMaster.tick()
            If (Date.UtcNow - scanLastTouchAll).TotalSeconds > 2 Then
                'touchAll()
                touchInactive()
                'debugShowStates()
                scanLastTouchAll = Date.UtcNow
            End If
            If (Date.UtcNow - scanLastActivity).TotalSeconds > 5 Then
                touchAll()
                taskSleepLonger()
            End If

            tickCounter += 1
            If tickCounter Mod 48 = 0 Then taskSleep()
        Loop

        serversCountOnline = 0
        SyncLock targetsCollectionLock
            For Each target As ServerScannerWorker In targets.Values
                If target.getState().done AndAlso target.caps.isOnline Then
                    serversCountOnline += 1
                End If
            Next
        End SyncLock
        scanEnd = Date.UtcNow

        updateScanInfo()

        RaiseEvent OnScanComplete(serversCountTotal, serversCountOnline, scanEnd - scanStart)
        debugWriteLine("Scan done in {0} seconds, {1} network ticks.", Math.Round((scanEnd - scanStart).TotalSeconds), tickCounter)
        debugShowStates()
        disposeTargets()
        disposeSockets()

    End Sub


    Public Sub packetHandler(packet() As Byte, source As IPEndPoint) Handles socketMaster.PacketReceived
        Dim info As Hashtable, fullPacket As String = "", target As ServerScannerWorker, ipString As String

        Dim packetString As String
        ipString = source.ToString
        If Not targets.ContainsKey(ipString) Then Return ' unknown source!! we got packet that wasn't sent by any of the queried servers (haxerz?)
        target = targets(ipString)
        Try
            If target.getState().done Then Return ' prevent processing the packets from targets in "done" state
            If packet.Length = 0 Then Return

            packetString = Unicode.GetString(Encoding.Convert(Encoding.GetEncoding(1252), Encoding.Unicode, packet))

            fullPacket = fragmentedPackets(ipString) & packetString

            'info = parseQuery(fullPacket)
            target.incomingPacketObj = New UTQueryPacket(fullPacket)

            target.incomingPacket = target.incomingPacketObj.convertToHashtablePacket()
            target.tick()

            scanLastActivity = Date.UtcNow
            fragmentedPackets(ipString) = ""
            'If target.isDone Then
            '    Console.WriteLine("Done: {0}", ipString)
            'End If

        Catch ex As UTQueryResponseIncompleteException
            fragmentedPackets(source.ToString) = fullPacket
        Catch ex As UTQueryInvalidResponseException ' we found a port that belongs to other service, so we're not going to bother it anymore
            target.logDbg("InvalidQuery: found unknown service")
            target.abortScan()
            socketMaster.addIgnoredIp(target.address)
        End Try
        'debugShowStates()

    End Sub


    Protected Sub initSockets()
        socketMaster = New SocketMaster
        'AddHandler socketMaster.PacketReceived, AddressOf packetHandler
    End Sub
    Protected Sub disposeSockets()
        socketMaster.clearIgnoredIps()
        socketMaster = Nothing
    End Sub

    Private Sub debugShowStates()
        Dim bas, inf, infex, pl, ru, tt, don, onl, ttp As Integer
        Dim st As ServerScannerWorkerState
        SyncLock targetsCollectionLock
            For Each t As ServerScannerWorker In targets.Values
                st = t.getState
                bas += IIf(st.hasBasic, 1, 0)
                inf += IIf(st.hasInfo, 1, 0)
                infex += IIf(st.hasInfoExtended, 1, 0)
                pl += IIf(st.hasPlayers, 1, 0)
                ru += IIf(st.hasRules, 1, 0)
                tt += IIf(st.hasTimeTest, 1, 0)
                don += IIf(st.done, 1, 0)
                onl += IIf(t.caps.isOnline, 1, 0)
                ttp += IIf(t.caps.timeTestPassed, 1, 0)
            Next
        End SyncLock
        debugWriteLine("States: BAS {0} INF {1} INFEX {2} PL {3} RU {4} TT {5} TTP {8} DO {6} ON {7}", bas, inf, infex, pl, ru, tt, don, onl, ttp)
    End Sub

    Protected Sub initTargets(serverList As List(Of String))
        SyncLock targetsCollectionLock
            For Each server In serverList
                targets(server) = New ServerScannerWorker(Me, server)
                targets(server).setSocket(socketMaster)
                fragmentedPackets(server) = ""
            Next
        End SyncLock
    End Sub

    Protected Sub disposeTargets()
        SyncLock targetsCollectionLock
            For Each t As ServerScannerWorker In targets.Values
                Dim s = t.getState()
                If s.hasBasic AndAlso Not s.hasRules Then
                    'Debugger.Break()
                End If

            Next
            targets.Clear()
        End SyncLock

        fragmentedPackets.Clear()

    End Sub

    Protected Sub updateScanInfo()
        dynconfigSet("net.reaper.lastupdate", unixTime())
        With My.Application.Info
            dynconfigSet("net.reaper.scannerversion", .AssemblyName & "-" & .Version.Major & "." & .Version.Minor & "." & .Version.Build)
            dynconfigSet("net.reaper.scannerapptitle", .Title)
        End With
        dynconfigSet("net.reaper.scaninfo.serversscanned", serversCountTotal)
        dynconfigSet("net.reaper.scaninfo.serversonline", serversCountOnline)
        dynconfigSet("net.reaper.scaninfo.scantime", (scanEnd - scanStart).TotalSeconds)
        dynconfigSet("net.reaper.scaninfo.netticks", tickCounter)
        dynconfigSet("net.reaper.scaninterval", scanInterval)
        dynconfigSet("net.reaper.masterservermanager.lastupdate", unixTime(masterServerLastUpdate))
        dynconfigSet("net.reaper.masterservermanager.numservers", masterServerQuery.Count)
        debugWriteLine("ScanInfoUpdated")
    End Sub

    Protected Function getRecentlyScannedServerList(Optional seconds As Integer = 86400, Optional includeAncientServers As Boolean = False) As List(Of String)
        Static ancientTimes As Integer = unixTime(Date.Parse("1.01.2009 0:00:00")) ' include servers with invalid last scan date due to bios time reset
        Dim recentServersCmd As New MySqlCommand("Select `address`,`rules` from `serverinfo` where `lastscan`>@lastscan", db.dbh)
        If includeAncientServers Then recentServersCmd.CommandText &= " or `lastscan` < @ancientTimes"
        recentServersCmd.CommandType = CommandType.Text
        recentServersCmd.Parameters.AddWithValue("@lastscan", unixTime() - seconds)
        recentServersCmd.Parameters.AddWithValue("@ancientTimes", ancientTimes)
        Dim queryAdapter = New MySqlDataAdapter(recentServersCmd)
        Dim table = New DataTable
        SyncLock db.dbh
            queryAdapter.Fill(table)
        End SyncLock
        Dim recentServers = New List(Of String)
        Dim json As New System.Web.Script.Serialization.JavaScriptSerializer()
        Dim rules As Hashtable

        For Each server As DataRow In table.Rows
            Dim fullQueryIp = server("address")
            Try
                If Not IsDBNull(server("rules")) AndAlso server("rules") <> "" Then
                    rules = json.Deserialize(Of Hashtable)(server("rules"))
                    If Not IsNothing(rules) AndAlso rules.ContainsKey("queryport") Then
                        Dim ip = getIp(server("address"))
                        fullQueryIp = ip & ":" & rules("queryport")
                    End If
                End If
            Catch e As Exception
            End Try

            recentServers.Add(fullQueryIp)
        Next
        Return recentServers
    End Function

    Protected Function getServersPendingQueue() As List(Of String)
        Dim recentServersCmd As New MySqlCommand("Select `address` from `serverqueue`", db.dbh)
        recentServersCmd.CommandType = CommandType.Text
        Dim queryAdapter = New MySqlDataAdapter(recentServersCmd)
        Dim table = New DataTable
        SyncLock db.dbh
            queryAdapter.Fill(table)
        End SyncLock
        Dim queueServers = New List(Of String)
        If table.Rows.Count > 0 Then
            Dim queueClearCmd As New MySqlCommand("Delete from `serverqueue` where true", db.dbh)
            queueClearCmd.ExecuteNonQuery()
            For Each server As DataRow In table.Rows
                queueServers.Add(server("address"))
            Next
        End If
        debugWriteLine("getServersPendingQueue: {0}", queueServers.Count)
        Return queueServers
    End Function

    Protected Sub touchAll(Optional init As Boolean = False)
        SyncLock targetsCollectionLock
            If init Then
                debugWriteLine("touchAll DOMINO MODE")
                Dim lastPacketBufferFlush As Date = Date.UtcNow
                For Each target As ServerScannerWorker In targets.Values
                    target.tick()
                    If (Date.UtcNow - lastPacketBufferFlush).TotalMilliseconds > 50 Then
                        socketMaster.tick()
                        taskSleep()
                        lastPacketBufferFlush = Date.UtcNow
                    End If
                Next
            Else
                'debugWriteLine("touchAll CHECK MODE")
                For Each target As ServerScannerWorker In targets.Values
                    target.tick()
                Next
            End If
        End SyncLock
    End Sub

    Protected Sub touchInactive()
        For Each target As ServerScannerWorker In targets.Values
            If (Date.UtcNow - target.lastActivity).TotalSeconds > 15 Then
                target.tick()
            End If
        Next
    End Sub

    Friend Sub logWriteLine(ByVal message As String)
        log.WriteLine("ServerScanner: " & message)
    End Sub

    Friend Sub logWriteLine(ByVal format As String, ByVal ParamArray arg As Object())
        log.WriteLine("ServerScanner: " & format, arg)
    End Sub

    Friend Sub debugWriteLine(ByVal message As String)
        log.DebugWriteLine("ServerScanner: " & message)
    End Sub

    Friend Sub debugWriteLine(ByVal format As String, ByVal ParamArray arg As Object())
        log.DebugWriteLine("ServerScanner: " & format, arg)
    End Sub

    Protected Sub taskSleep() 'suspends program for 1 ms, since we don't need 100% of cpu power
        ' todo: replace with timer queue api for more predictable execution times
        ' and NO, timeBeginPeriod(1) is not a good solution!!
        System.Threading.Thread.CurrentThread.Join(1)
    End Sub

    Protected Sub taskSleepLonger() 
        System.Threading.Thread.CurrentThread.Join(200)
    End Sub

    Private Sub ServerScanner_OnScanBegin(serverCount As Integer) Handles Me.OnScanBegin
        transaction = db.dbh.BeginTransaction()
        db.dbtr = transaction
    End Sub


    Private Sub ServerScanner_OnScanComplete(scannedServerCount As Integer, onlineServerCount As Integer, elapsedTime As System.TimeSpan) Handles Me.OnScanComplete
        transaction.Commit()
        transaction.Dispose()
        transaction = Nothing
        db.dbtr = transaction
    End Sub

    Private Sub masterServerQuery_OnMasterServerManagerRequest(masterServers As List(Of MasterServerInfo)) Handles masterServerQuery.OnMasterServerManagerRequest
        logWriteLine("Master server query...")
        dyncfg.setProperty("net.reaper.masterservermanager.nummasters", masterServers.Count)
        dyncfg.unsetProperty("net.reaper.masterservermanager.server")

    End Sub

    Private Sub masterServerQuery_OnMasterServerManagerRequestComplete(serverList As System.Collections.Generic.List(Of String)) Handles masterServerQuery.OnMasterServerManagerRequestComplete
        logWriteLine("Received {0} servers, performing scan...", serverList.Count)
    End Sub

    Private Sub masterServerQuery_OnMasterServerQuery(serverInfo As MasterServerInfo) Handles masterServerQuery.OnMasterServerQuery
        logWriteLine("MasterQuery ( " & serverInfo.serverClassName & " , " & serverInfo.serverIp & ":" & serverInfo.serverPort & " ) ")
        dyncfg.setProperty("net.reaper.masterservermanager.server." & serverInfo.serverId & ".checked", unixTime())
        dyncfg.setProperty("net.reaper.masterservermanager.server." & serverInfo.serverId & ".info", _
            serverInfo.serverIp & ":" & serverInfo.serverPort)
    End Sub


    Private Sub masterServerQuery_OnMasterServerQueryParsed(serverInfo As MasterServerInfo, serverList As System.Collections.Generic.List(Of String)) Handles masterServerQuery.OnMasterServerQueryListReceived
        dyncfg.setProperty("net.reaper.masterservermanager.server." & serverInfo.serverId & ".lastseen", unixTime())
        dyncfg.setProperty("net.reaper.masterservermanager.server." & serverInfo.serverId & ".lastsync", unixTime())
        dyncfg.setProperty("net.reaper.masterservermanager.server." & serverInfo.serverId & ".serversnum", serverList.Count)
        logWriteLine("Got {0} servers.", serverList.Count)
    End Sub
    Private Sub masterServerQuery_OnMasterServerQueryFailure(serverInfo As MasterServerInfo, thrownException As System.Exception) Handles masterServerQuery.OnMasterServerQueryFailure
        logWriteLine("Query failed for ( {0}:{1} ) : {2}", serverInfo.serverIp, serverInfo.serverPort, thrownException.Message)
    End Sub

    Private Sub masterServerQuery_OnMasterServerPing(serverInfo As MasterServerInfo, online As Boolean) Handles masterServerQuery.OnMasterServerPing
        debugWriteLine("PingingRemoteMasterServer: {0}", serverInfo.serverAddress)
        dyncfg.setProperty("net.reaper.masterservermanager.server." & serverInfo.serverId & ".checked", unixTime())
        If online Then
            dyncfg.setProperty("net.reaper.masterservermanager.server." & serverInfo.serverId & ".lastseen", unixTime())
        End If
    End Sub
#Region "Dynconfig"
    Public Function dynconfigGet(key As String)
        'Dim dynconfigCmd As New MySqlCommand("Select `data` from `utt_info` where `key`=@key", db.dbh)
        'dynconfigCmd.Parameters.AddWithValue("@key", key)
        'SyncLock db.dbh)
        'dynconfigGet = dynconfigCmd.ExecuteScalar()
        'End SyncLock db.dbh)
        'dynconfigCmd.Dispose()
        Return dyncfg.getProperty(key)
    End Function

    Public Sub dynconfigSet(key As String, data As String, Optional priv As Boolean = False)
        dyncfg.setProperty(key, data, priv)
        'Dim dynconfigCmd As New MySqlCommand("Replace into `utt_info`(`key`,`data`,`private`) values(@key,@data,@private)", db.dbh)
        'dynconfigCmd.Parameters.AddWithValue("@key", key)
        'dynconfigCmd.Parameters.AddWithValue("@data", data)
        'dynconfigCmd.Parameters.AddWithValue("@private", priv)
        'SyncLock db.dbh)
        'dynconfigCmd.ExecuteNonQuery()
        'End SyncLock db.dbh)
        'dynconfigCmd.Dispose()
    End Sub

#End Region

#Region "IDisposable"
    Public Sub Dispose() Implements IDisposable.Dispose
        Dispose(True)
        GC.SuppressFinalize(Me)
    End Sub

    Protected Overridable Sub Dispose(disposing As Boolean)
        If disposed Then Return

        If disposing Then

        End If

        If Not IsNothing(transaction) Then
            transaction.Rollback()
            transaction.Dispose()
            transaction = Nothing
            db.dbtr = transaction
        End If
        disposed = True
    End Sub
#End Region


#Region "Database stuff"

    Protected Sub prepareDB()


        maybeCreateTables()
    End Sub


    Public Sub maybeCreateTables()
        SyncLock db.dbh
            db.execCmd("CREATE TABLE IF NOT EXISTS `playerinfo` (" & _
                " `id` int(11) NOT NULL," & _
                " `name` text," & _
                " `skindata` text," & _
                " `country` varchar(3) DEFAULT NULL," & _
                " PRIMARY KEY (`id`)" & _
                ") ENGINE=InnoDB DEFAULT CHARSET=latin1")

            db.execCmd("CREATE TABLE IF NOT EXISTS `playerhistory` (" & _
                " `recordid` int(11) NOT NULL," & _
                " `id` int(11) DEFAULT NULL," & _
                " `serverid` int(11) DEFAULT NULL," & _
                " `gameid` int(11) DEFAULT NULL," & _
                " `numupdates` smallint(6) DEFAULT NULL," & _
                " `lastupdate` int(11) DEFAULT NULL," & _
                " `enterdate` int(11) DEFAULT NULL," & _
                " `scorethismatch` int(11) DEFAULT NULL," & _
                " `pingsum` int(11) DEFAULT NULL," & _
                " `deathsthismatch` smallint(6) DEFAULT NULL," & _
                " `team` tinyint(4) DEFAULT NULL," & _
                " `flags` int(11) NOT NULL DEFAULT '0'," & _
                " PRIMARY KEY (`recordid`)," & _
                " KEY `ph_gid_idx` (`gameid`) USING HASH," & _
                " KEY `ph_pid_idx` (`id`) USING HASH," & _
                " KEY `ph_sid_idx` (`serverid`) USING HASH," & _
                " KEY `ph_lup_idx` (`lastupdate`) USING BTREE," & _
                " KEY `ph_pidsid_idx` (`serverid`,`id`)" & _
                ") ENGINE=InnoDB DEFAULT CHARSET=latin1")

            db.execCmd("CREATE TABLE IF NOT EXISTS `playerhistorythin` (" & _
                " `recordid` int(11) NOT NULL," & _
                " `id` int(11) DEFAULT NULL," & _
                " `serverid` int(11) DEFAULT NULL," & _
                " `gameid` int(11) DEFAULT NULL," & _
                " `numupdates` smallint(6) DEFAULT NULL," & _
                " `lastupdate` int(11) DEFAULT NULL," & _
                " `enterdate` int(11) DEFAULT NULL," & _
                " `scorethismatch` int(11) DEFAULT NULL," & _
                " `pingsum` int(11) DEFAULT NULL," & _
                " `deathsthismatch` smallint(6) DEFAULT NULL," & _
                " `team` tinyint(4) DEFAULT NULL," & _
                " `flags` int(11) NOT NULL DEFAULT '0'," & _
                " PRIMARY KEY (`recordid`)," & _
                " KEY `phthin_gid_idx` (`gameid`) USING HASH," & _
                " KEY `phthin_pid_idx` (`id`) USING HASH," & _
                " KEY `phthin_sid_idx` (`serverid`) USING HASH," & _
                " KEY `phthin_lup_idx` (`lastupdate`) USING BTREE" & _
                ") ENGINE=InnoDB DEFAULT CHARSET=latin1")

            db.execCmd("CREATE TABLE IF NOT EXISTS `serverinfo` (" & _
                " `serverid` int(11) NOT NULL," & _
                " `address` text," & _
                " `name` text," & _
                " `rules` text," & _
                " `lastscan` int(11) NOT NULL," & _
                " `lastrfupdate` int(11) NOT NULL," & _
                " `rfscore` int(11) NOT NULL," & _
                " `uplayers` int(11) NOT NULL," & _
                " `country` varchar(3) NOT NULL," & _
                " `gamename` varchar(20) NOT NULL," & _
                " PRIMARY KEY (`serverid`)" & _
                ") ENGINE=InnoDB DEFAULT CHARSET=latin1")

            db.execCmd("CREATE TABLE IF NOT EXISTS `serverhistory` (" & _
                " `gameid` int(11) NOT NULL AUTO_INCREMENT," & _
                " `serverid` int(11) DEFAULT NULL," & _
                " `date` int(11) DEFAULT NULL," & _
                " `mapname` text," & _
                " PRIMARY KEY (`gameid`)," & _
                " KEY `sh_sid_idx` (`serverid`) USING HASH," & _
                " KEY `sh_dat_idx` (`date`) USING BTREE," & _
                " KEY `sh_map_idx` (`mapname`(40))" & _
                ") ENGINE=InnoDB DEFAULT CHARSET=latin1")

            db.execCmd("CREATE TABLE IF NOT EXISTS `playerstats` (" & _
                " `playerid` int(11) NOT NULL," & _
                " `serverid` int(11) NOT NULL," & _
                " `time` int(11) NOT NULL," & _
                " `numupdates` int(11) NOT NULL," & _
                " `deaths` int(11) NOT NULL," & _
                " `score` bigint(20) NOT NULL," & _
                " `lastgame` int(11) NOT NULL," & _
                " PRIMARY KEY (`serverid`,`playerid`)," & _
                " KEY `ps_sid_idx` (`serverid`)," & _
                " KEY `ps_pid_idx` (`playerid`)" & _
                ") ENGINE=InnoDB DEFAULT CHARSET=latin1")

            db.execCmd("CREATE TABLE IF NOT EXISTS `utt_info` (" & _
                " `key` varchar(48) NOT NULL," & _
                " `data` text," & _
                " `private` boolean," & _
                " PRIMARY KEY (`key`)," & _
                " UNIQUE KEY `key` (`key`)" & _
                ") ENGINE=InnoDB DEFAULT CHARSET=latin1")
        End SyncLock
    End Sub

#End Region



End Class

Public Class ServerScannerWorker
    Dim socket As SocketMaster
    Public scannerMaster As ServerScanner

    Public info As Hashtable
    Public players As List(Of Hashtable)
    Public rules As Hashtable
    'Public gameinfo As Hashtable 'gamemode-specific info

    Public firstTimeTest, secondTimeTest As Single
    Public firstTimeTestLocal, secondTimeTestLocal, infoSentTimeLocal As DateTime

    Public packetsSent As Integer = 0
    Public packetsReceived As Integer = 0
    Protected challenge As String

    Private state As ServerScannerWorkerState
    Public caps As ServerCapabilities
    Public address As String
    Public dbAddress As String ' ip with hostport+1 OR ip with queryport
    Public incomingPacket As Hashtable
    Friend incomingPacketObj As UTQueryPacket
    Private resendAttempts As Integer = 0
    Friend lastActivity As Date
    Protected saver As SaveGame
    Protected gamemodeQuery As GamemodeSpecificQuery

    Public Sub New(master As ServerScanner, serverAddress As String)
        address = serverAddress
        dbAddress = serverAddress
        scannerMaster = master
        With caps
            .hasPropertyInterface = True
            .supportsRules = True
        End With

        state.starting = True
        saver = New SaveGame(Me)

        challenge = generateChallenge()
    End Sub

    Public Sub tick()
        If Not state.done Then
            If state.starting Then
                sendRequest()
                state.starting = False
            Else

                If Not IsNothing(incomingPacket) Then ' we received a full response from server
                    packetReceived()
                    sendRequest()
                    'resendAttempts = 0
                Else ' haven't got anything, just checking for timeouts
                    If (Date.UtcNow - lastActivity).TotalSeconds > 10 AndAlso (Date.UtcNow - scannerMaster.scanLastTouchAll).TotalSeconds < 5 Then
                        'If (resendAttempts >= 1) Then 'packet re-sending feature is not working as intended, we're aborting the scan after detecting the first timeout
                        If Not skipStepIfOptional() Then
                            'logDbg("NonOptionalStepFailed")
                            abortScan()
                        End If
                        'Else
                        '    resendAttempts += 1
                        '    sendRequest()
                        'End If

                    End If
                End If
            End If
        End If
        If Not saver.state.done Then
            saver.tick()
        End If
    End Sub

    Public Function isDone()
        Return state.done
    End Function

    Public Function getState() As ServerScannerWorkerState
        Return state
    End Function

    Public Sub setSocket(ByRef master As SocketMaster)
        socket = master
    End Sub

    Private Sub sendRequest()
        Const xsqSuffix = "XServerQuery"

        If isInRequestState() Then Return ' remove this when implementing resend feature

        With state
            If Not .hasBasic Then
                serverSend("\basic\\secure\" & challenge)
                .requestingBasic = True
            ElseIf Not .hasInfo Then
                serverSend("\info\" & IIf(info("gamename") = "ut", xsqSuffix, ""))
                .requestingInfo = True
                infoSentTimeLocal = Date.UtcNow
            ElseIf Not .hasInfoExtended AndAlso Not .hasTimeTest AndAlso caps.hasPropertyInterface Then
                firstTimeTestLocal = Date.UtcNow ' AKA timestamp of sending the extended info request
                gamemodeQuery = GamemodeSpecificQuery.getQueryObjectForContext(Me)
                Dim gamemodeAdditionalRequests As String = "", otherAdditionalRequests As String = ""
                If Not IsNothing(gamemodeQuery) Then
                    gamemodeAdditionalRequests = gamemodeQuery.getInfoRequestString()
                    caps.gamemodeExtendedInfo = True
                End If
                If Not info.ContainsKey("timelimit") Then
                    otherAdditionalRequests &= "\game_property\TimeLimit\"
                End If

                serverSend("\game_property\NumPlayers\\game_property\NumSpectators\" _
                           & "\game_property\GameSpeed\\game_property\CurrentID\\game_property\bGameEnded\\game_property\bOvertime\\game_property\ElapsedTime\\game_property\RemainingTime\" _
                           & otherAdditionalRequests _
                           & gamemodeAdditionalRequests)
                .requestingInfoExtended = True
                'ElseIf Not .hasTimeTest AndAlso caps.hasPropertyInterface Then
                '    secondTimeTestLocal = Date.UtcNow
                '    serverSend("\level_property\TimeSeconds\\echo\TimeTestWillBeRemovedSoon\")
                '    .requestingTimeTest = True
            ElseIf Not .hasPlayers AndAlso info("numplayers") <> 0 AndAlso Not caps.fakePlayers Then
                serverSend("\players\" & IIf(caps.hasXSQ, xsqSuffix, ""))
                .requestingPlayers = True
            ElseIf Not .hasRules AndAlso caps.supportsRules Then
                serverSend("\rules\" & IIf(caps.hasXSQ, xsqSuffix, ""))
                .requestingRules = True

            Else
                .done = True
            End If


        End With
        lastActivity = Date.UtcNow
    End Sub

    Private Sub serverSend(packet As String)
        Try
            socket.sendTo(address, packet)
            packetsSent += 1
        Catch e As Sockets.SocketException
            logDbg("ServerSendExc: " & e.Message)
            abortScan()
        End Try
    End Sub

    Private Sub packetReceived()
        With state
            If .requestingBasic Then
                parseBasic()
            ElseIf .requestingInfo Then
                parseInfo()
            ElseIf .requestingInfoExtended Then
                parseInfoExtended()
            ElseIf .requestingPlayers Then
                parsePlayers()
            ElseIf .requestingRules Then
                parseRules()
                'ElseIf .requestingTimeTest Then
                '    parseTimeTest()
            Else
                'Debugger.Break()
            End If
        End With
        lastActivity = Date.UtcNow
        incomingPacket = Nothing
        resetRequestFlags()
        packetsReceived += 1
    End Sub

    Private Sub parseBasic()

        'If Not incomingPacket.ContainsKey("gamename") OrElse Not ( _
        '    incomingPacket.ContainsKey("echo") OrElse incomingPacket.ContainsKey("echo_reply") OrElse _
        '    incomingPacket.ContainsKey("echo_replay") OrElse incomingPacket.ContainsKey("echoresponse") OrElse _
        '    incomingPacket.ContainsKey("ignore") OrElse incomingPacket.ContainsKey("reply") OrElse _
        '    incomingPacket.ContainsKey("ignoring")) Then

        If Not incomingPacket.ContainsKey("gamename") Then
            logDbg("NoGamename: " & incomingPacketObj.ToString)
            abortScan()
        Else
            Dim validServer As Boolean = False

            ' validate
            If Not incomingPacket.ContainsKey("validate") Then
                validServer = False
            ElseIf Len(incomingPacket("validate")) <> 8 OrElse Not MasterServerManager.gamespyKeys.ContainsKey(incomingPacket("gamename")) Then
                validServer = True
            Else
                Dim expectedResponse = gsenc(challenge, MasterServerManager.gamespyKeys(incomingPacket("gamename")).encKey)
                validServer = (expectedResponse = incomingPacket("validate"))
                'If Not validServer Then Debugger.Break()
            End If

            If Not validServer Then
                logDbg("InvalidServer: " & incomingPacketObj.ToString)
                abortScan()
            End If

            info = New Hashtable
            info("gamename") = incomingPacket("gamename")
            info("gamever") = incomingPacket("gamever")
            If incomingPacket.ContainsKey("minnetver") Then
                info("minnetver") = incomingPacket("minnetver")
            ElseIf incomingPacket.ContainsKey("mingamever") Then
                info("minnetver") = incomingPacket("mingamever")
            End If
            info("location") = incomingPacket("location")
            state.hasBasic = True
            caps.isOnline = True
            caps.version = info("gamever")
            caps.gameName = info("gamename")
        End If
    End Sub

    Private Sub parseInfo()
        If Not incomingPacket.ContainsKey("hostname") Then
            logDbg("NoHostname: " & incomingPacket.ToString)
            abortScan()
        Else
            For Each index In incomingPacket.Keys
                info(index) = incomingPacket(index)
            Next
            If info.ContainsKey("hostport") AndAlso IsNumeric(info("hostport")) Then
                dbAddress = getIp(address) & ":" & (Integer.Parse(info("hostport")) + 1)
            End If
            If info.ContainsKey("xserverquery") Then
                caps.hasXSQ = True
                Integer.TryParse(Replace(info("xserverquery"), ".", ""), caps.XSQVersion)
                caps.hasPropertyInterface = False
                caps.timeTestPassed = False
            End If
            state.hasInfo = True
        End If
    End Sub

    Private Sub parseInfoExtended()
        Try
            If Not incomingPacket.ContainsKey("gamespeed") OrElse incomingPacket("numplayers") = "*Private*" Then
                Throw New Exception("Incorrect extended info (gamespeed/numplayers)")
            End If

            info("__uttrealplayers") = incomingPacket("numplayers")
            info("__uttspectators") = incomingPacket("numspectators")
            info("__uttgamespeed") = incomingPacket("gamespeed")
            info("__uttgamecurrentid") = incomingPacket("currentid")
            info("bgameended") = incomingPacket("bgameended")
            info("bovertime") = incomingPacket("bovertime")
            info("elapsedtime") = incomingPacket("elapsedtime")
            info("remainingtime") = incomingPacket("remainingtime")
            If incomingPacket.ContainsKey("timelimit") Then info("timelimit") = incomingPacket("timelimit")
            Single.TryParse(incomingPacket("gamespeed"), caps.gameSpeed)
            If caps.gameSpeed = 0 Then
                Throw New Exception("Incorrect extended info (gamespeed=0)")
            Else
                'Single.TryParse(incomingPacket("timeseconds"), firstTimeTest)
                'info("__uttfirsttimetest") = firstTimeTest
                state.hasInfoExtended = True
                state.hasTimeTest = True
                caps.timeTestPassed = True
                ' fake players detection
                If info("numplayers") > incomingPacket("numplayers") + incomingPacket("numspectators") Then
                    caps.fakePlayers = True
                End If
            End If
            If caps.gamemodeExtendedInfo Then
                'gameinfo = New Hashtable
                gamemodeQuery.parseInfoPacket(incomingPacket)
            End If
        Catch e As Exception
            'firstTimeTest = -1
            state.hasTimeTest = True
            caps.timeTestPassed = False
            caps.hasPropertyInterface = False
            'logDbg(e.GetType.FullName & ": " & e.Message)
        End Try
    End Sub

    Private Sub parsePlayers()
        Dim playerid As Integer = 0, suffix As String, playerinfo As Hashtable
        Dim buggedPingCount As Integer = 0 ' 2016-03-18: skip scanning of broken servers (all players with ping 9999)
        players = New List(Of Hashtable)
        Try
            Do While incomingPacket.ContainsKey("player_" & playerid)
                suffix = "_" & playerid
                playerinfo = New Hashtable
                playerinfo("name") = incomingPacket("player" & suffix)
                playerinfo("ping") = incomingPacket("ping" & suffix)
                playerinfo("frags") = incomingPacket("frags" & suffix)
                playerinfo("mesh") = incomingPacket("mesh" & suffix)
                playerinfo("skin") = incomingPacket("skin" & suffix)
                playerinfo("face") = incomingPacket("face" & suffix)
                playerinfo("team") = incomingPacket("team" & suffix)

                If (Integer.Parse(playerinfo("ping")) > 100000) Then
                    buggedPingCount += 1
                End If

                If caps.hasXSQ Then
                    playerinfo("countryc") = incomingPacket("countryc" & suffix)
                    playerinfo("deaths") = incomingPacket("deaths" & suffix)
                    If caps.XSQVersion >= 200 Then
                        playerinfo("time") = incomingPacket("time" & suffix)
                    Else
                        playerinfo("time") = incomingPacket("time" & suffix) * 60
                    End If
                    'playerinfo("health") = incomingPacket("health" & suffix)
                End If
                players.Add(playerinfo)
                playerid += 1
            Loop
            If buggedPingCount > players.Count / 2 Then
                abortScan()
            End If
        Catch e As Exception
            'Debugger.Break()
            logDbg("ParsePlayersExc: " & e.Message)
        End Try
        state.hasPlayers = True
    End Sub

    Private Sub parseRules()
        rules = incomingPacket.Clone()

        info("__utthaspropertyinterface") = caps.hasPropertyInterface
        info("__utttimetestpassed") = caps.timeTestPassed

        state.hasRules = True
    End Sub

    ' time test is a special kind of check which determines 
    ' if we can trust the LevelInfo.TimeSeconds property 
    ' to get the match start time
    ' NO LONGER USED AS OF 24 JUL '15
    Private Sub parseTimeTest()
        Try
            Single.TryParse(incomingPacket("timeseconds"), secondTimeTest)

            caps.timeTestPassed = (firstTimeTest <> 0 AndAlso secondTimeTest <> 0 AndAlso secondTimeTest > firstTimeTest AndAlso _
                                   (secondTimeTest - firstTimeTest) / caps.gameSpeed < (secondTimeTestLocal - firstTimeTestLocal).TotalSeconds * 1.2)

            If caps.timeTestPassed Then
                info("__uttgamestart") = unixTime(secondTimeTestLocal) - Math.Round(secondTimeTest / caps.gameSpeed)
                info("__utttimetestdelta") = secondTimeTest - firstTimeTest
                info("__utttimetestdeltalocal") = (secondTimeTestLocal - firstTimeTestLocal).TotalSeconds
            Else
                'Console.WriteLine("{0} : {1} ", (secondTimeTest - firstTimeTest) / caps.gameSpeed, (secondTimeTestLocal - firstTimeTestLocal).TotalSeconds * 1.2)
            End If
        Catch e As Exception
            caps.timeTestPassed = False
        End Try
        state.hasTimeTest = True
    End Sub

    Private Function skipStepIfOptional()
        With state
            If .requestingInfoExtended Then
                .requestingInfoExtended = False
                .hasTimeTest = True
                caps.hasPropertyInterface = False
                caps.timeTestPassed = False
                lastActivity = Date.UtcNow
                sendRequest()
                Return True
            ElseIf .requestingTimeTest Then
                .requestingTimeTest = False
                .hasTimeTest = True
                caps.timeTestPassed = False
                lastActivity = Date.UtcNow
                sendRequest()
                Return True
            ElseIf .requestingRules Then
                .requestingRules = False
                caps.supportsRules = False
                .hasRules = False
                sendRequest()
                Return True
            End If

            'If Not .requestingBasic Then Debugger.Break()
        End With

        Return False
    End Function

    Private Sub resetRequestFlags()
        With state
            .requestingBasic = False
            .requestingInfo = False
            .requestingInfoExtended = False
            .requestingPlayers = False
            .requestingRules = False
            .requestingTimeTest = False
        End With
    End Sub

    Private Function isInRequestState()
        With state
            Return .requestingBasic Or _
            .requestingInfo Or _
            .requestingInfoExtended Or _
            .requestingPlayers Or _
            .requestingRules Or _
            .requestingTimeTest
        End With
    End Function

    Friend Sub abortScan()
        If Not state.done Then
            state.done = True
            saver.state.done = True
            caps.isOnline = False
            If Not state.requestingBasic Then
                logDbg("Aborting scan (" & state.ToString & ")")
            End If
        End If
    End Sub

    Public Overrides Function ToString() As String
        Return "ScannerWorker#" & address & "#"
    End Function

    Private Shared Function generateChallenge() As String
        Static allowedChars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"
        Static allowedCharsLen = Len(allowedChars)
        Return allowedChars(rand(1, allowedCharsLen)) & allowedChars(rand(1, allowedCharsLen)) & allowedChars(rand(1, allowedCharsLen)) & _
            allowedChars(rand(1, allowedCharsLen)) & allowedChars(rand(1, allowedCharsLen)) & allowedChars(rand(1, allowedCharsLen))
    End Function

    Private Shared Function rand(min As UInt32, max As UInt32) As UInt32
        Static randomGen = New System.Random()
        Return randomGen.next(min, max)
    End Function

    Protected Friend Sub logDbg(msg As String)
        scannerMaster.log.DebugWriteLine("ScannerSlave[{0}]: {1}", address, msg)
    End Sub

End Class

Public Structure ServerScannerConfig
    Dim scanInterval As Integer
    Dim masterServerUpdateInterval As Integer
    'Dim masterServerCacheFile As String
    'Dim masterServerGSListFile As String
    'Dim masterServerList As List(Of String)
    Dim masterServerManager As MasterServerManager
    Dim log As DeVlog
    Dim db As MySQLDB
    Dim iniFile As String
End Structure

Public Structure ServerScannerWorkerState
    Dim starting As Boolean
    Dim hasBasic As Boolean
    Dim hasInfo As Boolean
    Dim hasInfoExtended As Boolean
    Dim hasTimeTest As Boolean
    Dim hasPlayers As Boolean
    Dim hasRules As Boolean

    Dim requestingBasic As Boolean
    Dim requestingInfo As Boolean
    Dim requestingInfoExtended As Boolean
    Dim requestingTimeTest As Boolean
    Dim requestingPlayers As Boolean
    Dim requestingRules As Boolean

    Dim done As Boolean

    Public Overrides Function ToString() As String
        ToString = "ScannerWorkerState#"
        If requestingRules Then
            ToString &= "requestingRules"
        ElseIf requestingPlayers Then
            ToString &= "requestingPlayers"
        ElseIf requestingPlayers Then
            ToString &= "requestingTimeTest"
        ElseIf requestingInfoExtended Then
            ToString &= "requestingInfoExtended"
        ElseIf requestingInfo Then
            ToString &= "requestingInfo"
        ElseIf requestingBasic Then
            ToString &= "requestingBasic"
        ElseIf done Then
            ToString &= "done"
        Else
            ToString &= "???"
        End If
        ToString &= "#"
    End Function
End Structure

Public Structure ServerCapabilities
    Dim isOnline As Boolean
    Dim version As String
    Dim gameName As String
    Dim hasXSQ As Boolean
    Dim XSQVersion As Integer
    Dim hasPropertyInterface As Boolean
    Dim timeTestPassed As Boolean
    Dim gameSpeed As Single
    Dim supportsRules As Boolean
    Dim gamemodeExtendedInfo As Boolean
    Dim fakePlayers As Boolean

    Public Overrides Function ToString() As String
        ToString = "ServerCapabilities{ "
        If isOnline Then ToString &= "isOnline gameName=" & gameName & " version=" & version & " "
        If hasXSQ Then ToString &= "hasXSQ=" & XSQVersion & " "
        If hasPropertyInterface Then ToString &= "hasPropertyInterface "
        If timeTestPassed Then ToString &= "timeTestPassed gameSpeed=" & gameSpeed & " "
        ToString &= "}"
    End Function
End Structure