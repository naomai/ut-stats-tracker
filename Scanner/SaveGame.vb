Imports MySql.Data.MySqlClient
Imports System.Threading

Public Class SaveGame
    Protected scannerSlave As ServerScannerWorker
    Public db As MySQLDB
    Dim uttServerId As Int32
    Dim uttGameId As UInt32
    Dim uttServerScanTime As Int64
    Public state As SaveGameWorkerState
    Dim dbLocked As Boolean = False



    Public Sub New(scannerSlave As ServerScannerWorker)
        Me.scannerSlave = scannerSlave
        db = scannerSlave.scannerMaster.db
        uttServerId = Math.Abs(CRC32(scannerSlave.address))
    End Sub

    Public Sub tick()
        Dim scannerState = scannerSlave.getState()
        If Not state.done Then
            If Not state.savedInfo Then tryUpdateInfo()
            If Not state.savedRules Then tryUpdateRules()
            If Not state.savedGameInfo Then tryUpdateGameInfo()
            If Not state.savedPlayers Then tryUpdatePlayerInfo()
            If Not state.savedCumulativeStats Then tryUpdateCumulativePlayersStats()
            If Not state.savedScanInfo Then updateCurrentScanInfo()
            If state.savedInfo AndAlso state.savedRules AndAlso state.savedGameInfo And state.savedPlayers And state.savedCumulativeStats And state.savedScanInfo Then
                state.done = True
            End If
        End If
    End Sub


    Private Sub tryUpdateInfo()
        Dim infoUpdateCmd As MySqlCommand
        Dim scannerState = scannerSlave.getState()
        If scannerState.hasBasic AndAlso scannerState.hasInfo Then
            uttServerScanTime = unixTime(scannerSlave.infoSentTimeLocal)

            If scannerSlave.dbAddress <> scannerSlave.address Then uttServerId = Math.Abs(CRC32(scannerSlave.dbAddress))

            infoUpdateCmd = New MySqlCommand( _
                "Insert into `serverinfo` (`serverid`,`address`,`name`,`gamename`) values (@sid,@address,@name,@gamename) " & _
                " On duplicate key Update `name`=@name, `gamename`=@gamename", _
                db.dbh, db.dbtr)

            infoUpdateCmd.CommandType = CommandType.Text
            With (infoUpdateCmd.Parameters)
                .AddWithValue("@sid", uttServerId)
                .AddWithValue("@address", scannerSlave.dbAddress)
                .AddWithValue("@name", scannerSlave.info("hostname"))
                '.AddWithValue("@time", uttServerScanTime)
                .AddWithValue("@gamename", scannerSlave.info("gamename"))
            End With
            SyncLock db.dbh
                infoUpdateCmd.ExecuteNonQuery()
            End SyncLock

            state.savedInfo = True
        End If
    End Sub

    Private Sub tryUpdateRules()
        Dim cmd As MySqlCommand
        Dim rulesJoined As Hashtable
        Dim rulesJson As String
        Dim json As New System.Web.Script.Serialization.JavaScriptSerializer
        Dim scannerState = scannerSlave.getState()

        If Not scannerSlave.caps.supportsRules Then
            state.savedRules = True
            Return
        End If

        If scannerState.hasRules Then
            rulesJoined = scannerSlave.rules.Clone()
            For Each infoItem In scannerSlave.info.Keys
                rulesJoined(infoItem) = scannerSlave.info(infoItem)
            Next

            'utt haxes:
            rulesJoined("__uttlastupdate") = uttServerScanTime
            rulesJoined("queryport") = Split(scannerSlave.address, ":").Last
            If scannerSlave.caps.hasXSQ Then
                rulesJoined("__uttxserverquery") = "true"
            End If

            'rulesJson = JsonSerialize.jsonSerialize(rulesJoined)
            rulesJson = json.Serialize(rulesJoined)

            cmd = New MySqlCommand("Update `serverinfo` set `rules`=@rules where `serverid`=@serverid", db.dbh, db.dbtr)
            cmd.CommandType = CommandType.Text
            With cmd.Parameters
                .AddWithValue("@serverid", uttServerId)
                .AddWithValue("@rules", rulesJson)
            End With
            SyncLock db.dbh
                cmd.ExecuteNonQuery()
            End SyncLock
            state.savedRules = True
        End If
    End Sub

    Private Sub tryUpdateGameInfo() ' serverhistory
        Dim lastGameInfo As DataRow
        Dim gameInfoUpdateCmd As MySqlCommand
        Dim scannerState = scannerSlave.getState()
        'If scannerState.hasInfo AndAlso Not (scannerSlave.caps.hasPropertyInterface AndAlso Not scannerSlave.caps.timeTestPassed) Then
        If scannerState.hasInfo Then
            gameInfoUpdateCmd = New MySqlCommand("", db.dbh, db.dbtr)
            lastGameInfo = getLastGameInfo()

            Dim timeGameStart As Int64, thisGameCurrentID As Int32, lastGameCurrentID As Int32
            If IsNothing(lastGameInfo) Then
                state.isNewGame = True
            Else
                lastGameCurrentID = lastGameInfo("gamecurrentid")
                If scannerState.hasInfoExtended Then
                    thisGameCurrentID = scannerSlave.info("__uttgamecurrentid")
                Else
                    thisGameCurrentID = -1
                End If

                state.isNewGame = state.isNewGame OrElse (scannerSlave.info("numplayers") > 0 AndAlso lastGameCurrentID <> -1 AndAlso thisGameCurrentID <> -1 AndAlso thisGameCurrentID < lastGameCurrentID AndAlso lastGameInfo("mapname") = scannerSlave.info("mapname"))
            End If


            If scannerSlave.caps.timeTestPassed AndAlso scannerSlave.info.ContainsKey("elapsedtime") AndAlso Not scannerSlave.caps.fakePlayers Then
                'timeGameStart = scannerSlave.info("__uttgamestart")
                If IsNumeric(scannerSlave.info("elapsedtime")) AndAlso ( _
                        scannerSlave.info("elapsedtime") > 0 OrElse (scannerSlave.info("timelimit") * 60) - scannerSlave.info("remainingtime") < 60) Then
                    timeGameStart = unixTime(scannerSlave.firstTimeTestLocal) - scannerSlave.info("elapsedtime")
                ElseIf IsNumeric(scannerSlave.info("timelimit")) AndAlso scannerSlave.info("timelimit") > 0 AndAlso (scannerSlave.info("timelimit") * 60) - scannerSlave.info("remainingtime") > 60 Then ' elapsed time not implemented in gamemode?
                    timeGameStart = unixTime(scannerSlave.firstTimeTestLocal) - ((scannerSlave.info("timelimit") * 60) - scannerSlave.info("remainingtime"))
                Else
                    timeGameStart = 0
                End If
                state.isNewGame = state.isNewGame OrElse (scannerSlave.info("numplayers") > 0 AndAlso timeGameStart > 0 AndAlso lastGameInfo("date") < timeGameStart AndAlso Math.Abs(lastGameInfo("date") - timeGameStart) > 240) OrElse lastGameInfo("mapname") <> scannerSlave.info("mapname")
            Else
                state.isNewGame = state.isNewGame OrElse (scannerSlave.info("numplayers") > 0 AndAlso unixTime(scannerSlave.infoSentTimeLocal) - lastGameInfo("date") > 3600 * 4) OrElse lastGameInfo("mapname") <> scannerSlave.info("mapname")
                If state.isNewGame Then
                    timeGameStart = unixTime(scannerSlave.infoSentTimeLocal)
                Else
                    timeGameStart = lastGameInfo("date")
                End If
            End If

            If state.isNewGame Then
                If timeGameStart = 0 Then timeGameStart = unixTime()
                'If scannerSlave.address = "217.147.84.102:5556" Then Debugger.Break()
                ' If Not IsNothing(lastGameInfo) Then Console.WriteLine("{2} travel: {0}->{1} (delta: {3} tt: {4})", lastGameInfo("mapname"), info("mapname"), uttServerId, timeGameStart - lastGameInfo("date"), Math.Round(secondTimeTest - firstTimeTest))
                gameInfoUpdateCmd.CommandText = "Insert into `serverhistory` (`serverid`,`date`,`mapname`,`gamecurrentid`) values (@serverid,@date,@mapname,@gamecurrentid)"
                With gameInfoUpdateCmd.Parameters
                    .AddWithValue("@serverid", uttServerId)
                    .AddWithValue("@date", timeGameStart)
                    .AddWithValue("@mapname", scannerSlave.info("mapname"))
                    .AddWithValue("@gamecurrentid", thisGameCurrentID)
                End With
                SyncLock db.dbh
                    gameInfoUpdateCmd.ExecuteNonQuery()
                End SyncLock
                uttGameId = gameInfoUpdateCmd.LastInsertedId
            Else
                If thisGameCurrentID <> -1 AndAlso thisGameCurrentID > lastGameCurrentID Then
                    gameInfoUpdateCmd.CommandText = "Update `serverhistory` set `gamecurrentid` = @gamecurrentid where `gameid` = @uttgameid"
                    With gameInfoUpdateCmd.Parameters
                        .AddWithValue("@uttgameid", lastGameInfo("gameid"))
                        .AddWithValue("@gamecurrentid", thisGameCurrentID)
                    End With
                    SyncLock db.dbh
                        gameInfoUpdateCmd.ExecuteNonQuery()
                    End SyncLock
                End If
                uttGameId = lastGameInfo("gameid")
            End If
            state.savedGameInfo = True
        End If
    End Sub

    Private Sub tryUpdatePlayerInfo()
        Dim scannerState = scannerSlave.getState()
        If scannerState.hasInfo AndAlso (scannerSlave.info("numplayers") = 0 OrElse scannerSlave.caps.fakePlayers) Then
            state.savedPlayers = True
        End If

        If scannerState.hasPlayers AndAlso state.savedGameInfo Then
            For Each player In scannerSlave.players
                Dim uttPlayerId As Integer = getPlayerId(player)
                player("uttPlayerId") = uttPlayerId

                updatePlayerInfoEntry(player)
                updatePlayerHistoryEntry(player)
            Next
            state.savedPlayers = True
        End If
    End Sub

    Private Sub updatePlayerInfoEntry(player As Hashtable) ' `playerinfo` table
        Dim playerInfoUpdateCmd As MySqlCommand
        Dim countryString As String = ""
        Try
            playerInfoUpdateCmd = New MySqlCommand("Insert into `playerinfo` (`id`,`name`,`skindata`,`country`) values(@id,@name,@skindata,@country)", db.dbh, db.dbtr)
            playerInfoUpdateCmd.CommandType = CommandType.Text
            With playerInfoUpdateCmd.Parameters
                .AddWithValue("@id", player("uttPlayerId"))
                .AddWithValue("@name", player("name"))
                .AddWithValue("@skindata", player("mesh") & "|" & player("skin") & "|" & player("face"))
                If scannerSlave.caps.hasXSQ Then countryString = player("countryc")
                .AddWithValue("@country", countryString)
            End With
            SyncLock db.dbh
                playerInfoUpdateCmd.ExecuteNonQuery()
            End SyncLock
            playerInfoUpdateCmd.Dispose()
        Catch e As MySqlException When e.Number = 1062
            If scannerSlave.caps.hasXSQ AndAlso player("countryc") <> Nothing Then
                playerInfoUpdateCmd = New MySqlCommand("Update `playerinfo` set `country` = @country where `id`=@id", db.dbh, db.dbtr)
                playerInfoUpdateCmd.CommandType = CommandType.Text
                With playerInfoUpdateCmd.Parameters
                    .AddWithValue("@id", player("uttPlayerId"))
                    .AddWithValue("@country", countryString)
                End With
                SyncLock db.dbh
                    playerInfoUpdateCmd.ExecuteNonQuery()
                End SyncLock
                playerInfoUpdateCmd.Dispose()
            End If
        End Try
    End Sub

    Private Sub updatePlayerHistoryEntry(player As Hashtable) ' `playerhistory` table
        Dim playerInfoUpdateCmd As MySqlCommand, playerTimeOffset As Integer = 0
        If state.isNewGame AndAlso player.ContainsKey("time") Then
            playerTimeOffset = -player("time")
        End If
        Try
            playerInfoUpdateCmd = New MySqlCommand("Insert into `playerhistorythin` (`recordid`,`id`,`serverid`,`gameid`,`numupdates`,`lastupdate`,`enterdate`,`scorethismatch`,`deathsthismatch`,`pingsum`,`team`,`flags`) " & _
                                                   "values(@recordid,@id,@serverid,@gameid,1,@lastupdate,@enterdate,@score,@deaths,@ping,@team,@flags) " & _
                                                   "On duplicate key Update `numupdates`=`numupdates`+1, `lastupdate`=@lastupdate,`scorethismatch`=@score,`deathsthismatch`=@deaths,`pingsum`=`pingsum`+@ping,`team`=@team,`flags`=@flags", db.dbh, db.dbtr)
            playerInfoUpdateCmd.CommandType = CommandType.Text
            With playerInfoUpdateCmd.Parameters
                .AddWithValue("@recordid", player("uttPlayerId") Xor uttGameId)
                .AddWithValue("@id", player("uttPlayerId"))
                .AddWithValue("@serverid", uttServerId)
                .AddWithValue("@gameid", uttGameId)
                .AddWithValue("@lastupdate", uttServerScanTime + playerTimeOffset)
                .AddWithValue("@enterdate", uttServerScanTime + playerTimeOffset)
                .AddWithValue("@score", IIf(IsNumeric(player("frags")), player("frags"), "0"))
                .AddWithValue("@deaths", IIf(scannerSlave.caps.hasXSQ, player("deaths"), Nothing))
                .AddWithValue("@ping", player("ping"))
                .AddWithValue("@team", player("team"))
                .AddWithValue("@flags", 0)

            End With
            SyncLock db.dbh
                playerInfoUpdateCmd.ExecuteNonQuery()
            End SyncLock
            playerInfoUpdateCmd.Dispose()
        Catch e As MySqlException
            scannerSlave.logDbg("ErrorUpdatingPlayerHistory[" & player("id") & "," & player("name") & "]: " & e.Message)
        End Try
    End Sub

    Private Sub tryUpdateCumulativePlayersStats() ' update `playerstats` and move old records from `playerhistorythin` to `playerhistory`
        If state.savedPlayers AndAlso state.savedGameInfo Then
            'Try
            Dim oldPlayerRecordsCmd As New MySqlCommand("Select * from `playerhistorythin` Where `serverid`=@serverid and `gameid` <> @gameid", db.dbh, db.dbtr)
            oldPlayerRecordsCmd.CommandType = CommandType.Text
            oldPlayerRecordsCmd.Parameters.AddWithValue("@serverid", uttServerId)
            oldPlayerRecordsCmd.Parameters.AddWithValue("@gameid", uttGameId)

            Dim queryAdapter = New MySqlDataAdapter(oldPlayerRecordsCmd)
            Dim oldPlayerRecords = New DataTable
            SyncLock db.dbh
                queryAdapter.Fill(oldPlayerRecords)
            End SyncLock

            Dim cumulativeStatsUpdateCmd As New MySqlCommand("Insert into `playerstats` (`playerid`,`serverid`,`time`,`numupdates`,`deaths`,`score`,`lastgame`) " & _
                                                                            "values (@playerid,@serverid,@time,@numupdates,@deaths,@score,@lastgame) " & _
                                                                            "On duplicate key Update `time`=If(`time`>=0,`time`+@time,`numupdates`*@updateInterval+@time),`numupdates`=`numupdates`+@numupdates,`deaths`=`deaths`+@deaths,`score`=`score`+@score,`lastgame`=@lastgame", db.dbh, db.dbtr)
            cumulativeStatsUpdateCmd.CommandType = CommandType.Text



            For Each playerRecord As DataRow In oldPlayerRecords.Rows
                Dim gameTime = playerRecord("lastupdate") - playerRecord("enterdate")
                If gameTime < 0 Then
                    gameTime = 0
                    Debugger.Break() ' trying to find the cause of "[UTT_ACHTUNG!Corrupted timespan]"
                End If

                With cumulativeStatsUpdateCmd.Parameters
                    .AddWithValue("@playerid", playerRecord("id"))
                    .AddWithValue("@serverid", playerRecord("serverid"))
                    .AddWithValue("@time", gameTime)
                    .AddWithValue("@numupdates", playerRecord("numupdates"))
                    .AddWithValue("@deaths", playerRecord("deathsthismatch"))
                    .AddWithValue("@score", playerRecord("scorethismatch"))
                    .AddWithValue("@lastgame", playerRecord("gameid"))
                    .AddWithValue("@updateInterval", scannerSlave.scannerMaster.scanInterval)
                End With
                SyncLock db.dbh
                    cumulativeStatsUpdateCmd.ExecuteNonQuery()
                End SyncLock
                cumulativeStatsUpdateCmd.Parameters.Clear()
            Next
            oldPlayerRecordsCmd.Dispose()
            cumulativeStatsUpdateCmd.Dispose()
            Dim oldPlayerRecordsMoveCmd As New MySqlCommand("Insert into `playerhistory` Select * from `playerhistorythin` where `serverid`=@serverid and `gameid` <> @gameid " & _
                                                            "On duplicate key Update recordid = values(recordid),id = values(id),serverid = values(serverid),gameid = values(gameid)," & _
                                                            "numupdates = values(numupdates),lastupdate = values(lastupdate),enterdate = values(enterdate)," & _
                                                            "scorethismatch = values(scorethismatch),pingsum = values(pingsum),deathsthismatch = values(deathsthismatch)," & _
                                                            "team = values(team),flags = values(flags); " & _
                                                            "Delete from `playerhistorythin` where `serverid`=@serverid and `gameid` <> @gameid", db.dbh, db.dbtr)
            oldPlayerRecordsMoveCmd.CommandType = CommandType.Text
            oldPlayerRecordsMoveCmd.Parameters.AddWithValue("@serverid", uttServerId)
            oldPlayerRecordsMoveCmd.Parameters.AddWithValue("@gameid", uttGameId)
            SyncLock db.dbh
                oldPlayerRecordsMoveCmd.ExecuteNonQuery()
            End SyncLock
            oldPlayerRecordsMoveCmd.Dispose()

            state.savedCumulativeStats = True


            'Catch e As Exception
            '    Debugger.Break()
            'End Try
        End If
    End Sub

    Private Sub updateCurrentScanInfo()
        If state.savedCumulativeStats OrElse (state.savedInfo AndAlso scannerSlave.info("numplayers") = 0) Then
            Dim serverInfoScanUpdate As New MySqlCommand("Update `serverinfo` set `lastscan`=@lastscan where `serverid`=@serverid", db.dbh, db.dbtr)
            serverInfoScanUpdate.CommandType = CommandType.Text
            serverInfoScanUpdate.Parameters.AddWithValue("@serverid", uttServerId)
            serverInfoScanUpdate.Parameters.AddWithValue("@lastscan", uttServerScanTime)
            SyncLock db.dbh
                serverInfoScanUpdate.ExecuteNonQuery()
            End SyncLock
            serverInfoScanUpdate.Dispose()
            state.savedScanInfo = True
        End If
    End Sub

    Private Function getLastGameInfo() As DataRow
        Dim lastGameInfoQuery As New MySqlCommand("Select * from `serverhistory` where `serverid`=@serverid order by `gameid` desc limit 1", db.dbh, db.dbtr)
        lastGameInfoQuery.CommandType = CommandType.Text
        lastGameInfoQuery.Parameters.AddWithValue("@serverid", uttServerId)
        Dim queryAdapter = New MySqlDataAdapter(lastGameInfoQuery)
        Dim table = New DataTable
        SyncLock db.dbh
            queryAdapter.Fill(table)
        End SyncLock
        If table.Rows.Count > 0 Then
            Return table(0)
        Else
            Return Nothing
        End If
    End Function

    Private Function getLastPlayerRecord(playerId As Integer) As DataRow
        Dim lastPlayerRecordQuery As New MySqlCommand("Select *, 0 as `_archived` from `playerhistorythin` where `playerid`=@playerid order by `date` desc limit 1", db.dbh, db.dbtr)
        lastPlayerRecordQuery.CommandType = CommandType.Text
        lastPlayerRecordQuery.Parameters.AddWithValue("@playerid", playerId)
        Dim queryAdapter = New MySqlDataAdapter(lastPlayerRecordQuery)
        Dim table = New DataTable
        SyncLock db.dbh
            queryAdapter.Fill(table)
        End SyncLock
        If table.Rows.Count > 0 Then
            Return table(0)
        Else
            lastPlayerRecordQuery.CommandText = "Select *, 1 as `_archived` from `playerhistory` where `playerid`=@playerid order by `date` desc limit 1"
            table = New DataTable
            SyncLock db.dbh
                queryAdapter.Fill(table)
            End SyncLock
            If table.Rows.Count > 0 Then
                Return table(0)
            Else
                Return Nothing
            End If
        End If
    End Function


    Private Shared Function getPlayerId(playerInfo As Hashtable) As Int32
        If (nameIsComplicated(playerInfo("name"))) Then
            getPlayerId = Math.Abs(CRC32(LCase(playerInfo("name") & "|3456")))
        Else
            getPlayerId = Math.Abs(CRC32(LCase(playerInfo("name") & "|" & playerInfo("mesh"))))
        End If
    End Function

    Private Shared Function nameIsComplicated(pname As String) As Boolean
        Return Len(pname) >= 10 OrElse Text.RegularExpressions.Regex.IsMatch(pname, "[\[\]\(\)\{\}<>~`!@#\$%\^&\*\-=_/;:'"",\.\?]")
    End Function
End Class


Public Structure SaveGameWorkerState
    Dim savedInfo As Boolean
    Dim savedRules As Boolean
    Dim savedGameInfo As Boolean
    Dim savedPlayers As Boolean
    Dim savedCumulativeStats As Boolean
    Dim savedScanInfo As Boolean
    Dim isNewGame As Boolean
    Dim done As Boolean
End Structure