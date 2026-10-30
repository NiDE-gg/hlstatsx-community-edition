#pragma semicolon 1
#pragma newdecls required

#include <sourcemod>
#include <loghelper>

#define VERSION "2.2.1"

public Plugin myinfo =
{
        name = "SuperLogs: CSpromod",
        author = "NeoCortex, psychonic",
        description = "Rewrites the logs from CSProMod so HLStatsX:CE will understand them",
        version = VERSION,
        url = "http://www.sourcemod.net/"
};
 
public void OnPluginStart()
{
	CreateConVar("superlogs_cspromod_version", VERSION, "SuperLogs: CSpromod", FCVAR_NOTIFY);
	
	HookEvent("player_death", Event_PlayerDeath);
	HookEvent("player_connect", Event_PlayerConnect);
	HookEvent("player_disconnect", Event_PlayerDisconnect);
	HookEvent("player_team", Event_PlayerTeam);

	HookEvent("round_start", Event_RoundStart);
	HookEvent("round_end", Event_RoundEnd);

	CreateTimer(1.0, LogMap);
}

public Action LogMap(Handle timer)
{
	// Taken from superlogs-generic.sp by psychonic
	// Called 1 second after OnPluginStart since srcds does not log the first map loaded. Idea from Stormtrooper's "mapfix.sp" for psychostats
	LogMapLoad();
	return Plugin_Stop;
}

public void OnMapStart()
{
        // For loghelper
        GetTeams();
}

public void Event_PlayerConnect(Handle event, const char[] name, bool dontBroadcast)
{
	char cname[MAX_NAME_LENGTH];
	GetEventString(event, "name", cname, sizeof(cname));
	char steamid[24];
	GetEventString(event, "networkid", steamid, sizeof(steamid));
	char ip[32];
	GetEventString(event, "address", ip, sizeof(ip));
		
	LogToGame("\"%s<%d><%s><>\" connected, address \"%s\"", cname, GetEventInt(event, "userid"), steamid, ip);
}

public void Event_PlayerDisconnect(Handle event, const char[] name, bool dontBroadcast)
{
	// Everything comes from the event: the line needs the userid (not the client index),
	// and the client may already be gone
	char cname[MAX_NAME_LENGTH];
	GetEventString(event, "name", cname, sizeof(cname));
	char cauth[32];
	GetEventString(event, "networkid", cauth, sizeof(cauth));
	char creason[128];
	GetEventString(event, "reason", creason, sizeof(creason));

	LogToGame("\"%s<%d><%s><>\" disconnected (reason \"%s\")", cname, GetEventInt(event, "userid"), cauth, creason);
}

public void Event_PlayerDeath(Handle event, const char[] name, bool dontBroadcast)
{
	// Version 2 of this subroutine (using loghelper) is written by psychonic
	int victim = GetClientOfUserId(GetEventInt(event, "userid"));
	int attacker = GetClientOfUserId(GetEventInt(event, "attacker"));
	char AWeapon[64];
	GetEventString(event, "weapon", AWeapon, sizeof(AWeapon));

	char properties[12] = "";
	if (GetEventBool(event, "headshot"))
	{
		strcopy(properties, sizeof(properties), " (headshot)");
	}

	// Player_Suicide
	if (attacker == victim)
	{
		LogSuicide(victim, AWeapon, true, properties);
		return;
	}

	LogKill(attacker, victim, AWeapon, true, properties);
}

public void Event_RoundStart(Handle event, const char[] name, bool dontBroadcast)
{
	LogToGame("World triggered \"Round_Start\"");
}

public void Event_RoundEnd(Handle event, const char[] name, bool dontBroadcast)
{
	int winner = GetEventInt(event, "winner");
	if (winner == 2 || winner == 3)
	{
		LogTeamEvent(winner, "triggered", "Round_Win");
	}
	
	LogToGame("World triggered \"Round_End\"");
}

public void Event_PlayerTeam(Handle event, const char[] name, bool dontBroadcast)
{
	int client = GetClientOfUserId(GetEventInt(event, "userid"));
	int NTeam = GetEventInt(event, "team");
	char STeam[32];
	GetTeamName(NTeam, STeam, sizeof(STeam));

	LogPlayerEvent(client, "joined team", STeam, true);
}
