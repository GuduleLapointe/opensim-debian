/*
 * Copyright (c) Contributors, http://opensimulator.org/
 * See CONTRIBUTORS.TXT for a full list of copyright holders.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *	 * Redistributions of source code must retain the above copyright
 *	   notice, this list of conditions and the following disclaimer.
 *	 * Redistributions in binary form must reproduce the above copyright
 *	   notice, this list of conditions and the following disclaimer in the
 *	   documentation and/or other materials provided with the distribution.
 *	 * Neither the name of the OpenSimulator Project nor the
 *	   names of its contributors may be used to endorse or promote products
 *	   derived from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE DEVELOPERS ``AS IS'' AND ANY
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE CONTRIBUTORS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

/*
 * MuteListModule.cs
 *								Modified by Fumi.Iseki
*/

 
using System;
using System.Collections.Generic;
using System.Reflection;
using log4net;
using Nini.Config;
using OpenMetaverse;
using OpenSim.Framework;
//using OpenSim.Framework.Communications;
using OpenSim.Framework.Servers;
using OpenSim.Framework.Servers.HttpServer;
//using OpenSim.Framework.Client;
using OpenSim.Region.Framework.Interfaces;
using OpenSim.Region.Framework.Scenes;


namespace OpenSim.NSLModules.Messaging.MuteList
{
	public class NSLMuteListModule : IRegionModule
	{
		private static readonly ILog m_log = LogManager.GetLogger(MethodBase.GetCurrentMethod().DeclaringType);

		private IConfigSource m_config;
		private bool m_enabled = true;
		private List<Scene> m_SceneList = new List<Scene>();
		private string m_RestURL = String.Empty;


		public void Initialise(Scene scene, IConfigSource config)
		{
			if (!m_enabled) return;					 

			IConfig cnf = config.Configs["Messaging"];

			if (m_SceneList.Count==0) 
			{
				if (cnf == null)
				{
					m_enabled = false;
					return;
				}

				if (cnf != null && cnf.GetString("MuteListModule", "None") != "NSLMuteListModule")
				{
					m_enabled = false;
					return;
				}

				m_RestURL = cnf.GetString("MuteListURL", "");
				if (m_RestURL == "")
				{
					m_log.Error("[NSL MUTE LIST] Module was enabled, but no URL is given, disabling");
					m_enabled = false;
					return;
				}
			}

			if (!m_SceneList.Contains(scene)) m_SceneList.Add(scene);
			m_config = config;
   
			scene.EventManager.OnNewClient += OnNewClient;
		}


		public void PostInitialise()
		{
			if (!m_enabled) return;

			m_log.Debug("[NSL MUTE LIST] NSL MUTE LIST enabled");
		}


		public string Name
		{
			get { return "NSLMuteListModule"; }
		}


		public void Close()
		{
		}
	   

		public bool IsSharedModule
		{
			get { return true; }
		}


		ScenePresence FindPresence(UUID clientID)
		{
			ScenePresence p;

			foreach (Scene s in m_SceneList)
			{
				p = s.GetScenePresence(clientID);
				if (!p.IsChildAgent) return p;
			}
			return null;
		}  


		private void OnNewClient(IClientAPI client)
		{
			client.OnMuteListRequest 	 += OnMuteListRequest;
			client.OnUpdateMuteListEntry += OnUpdateMuteListEntry; 
			client.OnRemoveMuteListEntry += OnRemoveMuteListEntry;
		}



		//////////////////////////////////////////////////////////////////////////////////////////////////////////
		//
		//

	  	public void OnUpdateMuteListEntry(IClientAPI client, UUID MuteID, string Name, int Type, UUID AgentID) 
	   	{
			//m_log.DebugFormat("[NSL MUTE LIST] OnUpdateMuteListEntry {0}, {1}, {2}, {3}", MuteID.ToString(), Name, Type.ToString(), AgentID.ToString());

	   		GridMuteList ml = new GridMuteList(AgentID, MuteID, Name, Type, 0);
			//bool success = SynchronousRestObjectPoster.BeginPostObject<GridMuteList, bool>("POST", m_RestURL+"/UpdateList/", ml);
			bool success = SynchronousRestObjectRequester.MakeRequest<GridMuteList, bool>("POST", m_RestURL+"/UpdateList/", ml);
		}


	   	public void OnRemoveMuteListEntry(IClientAPI client, UUID MuteID, string Name, UUID AgentID)
	   	{
			//m_log.DebugFormat("[NSL MUTE LIST] OnRemoveMuteListEntry {0}, {1}, {2}", MuteID.ToString(), Name, AgentID.ToString());

	   		GridMuteList ml = new GridMuteList(AgentID, MuteID, Name, 0, 0);
			//bool success = SynchronousRestObjectPoster.BeginPostObject<GridMuteList, bool>("POST", m_RestURL+"/DeleteList/", ml);
			bool success = SynchronousRestObjectRequester.MakeRequest<GridMuteList, bool>("POST", m_RestURL+"/DeleteList/", ml);
		}


		private void OnMuteListRequest(IClientAPI client, uint crc)
		{
			//m_log.DebugFormat("[NSL MUTE LIST] Got MUTE LIST request for crc {0}", crc);

			int cnt = 0;
			string str = "";
			string url = m_RestURL + "/RequestList/";

			//List<GridMuteList> mllist = SynchronousRestObjectPoster.BeginPostObject<UUID, List<GridMuteList>>("POST", url, client.AgentId);
			List<GridMuteList> mllist = SynchronousRestObjectRequester.MakeRequest<UUID, List<GridMuteList>>("POST", url, client.AgentId);
			while (mllist==null && cnt<10) {		// retry
				//mllist = SynchronousRestObjectPoster.BeginPostObject<UUID, List<GridMuteList>>("POST", url, client.AgentId);
				mllist = SynchronousRestObjectRequester.MakeRequest<UUID, List<GridMuteList>>("POST", url, client.AgentId);
				cnt++;
			}

			if (mllist!=null) {
				foreach (GridMuteList ml in mllist)
				{
					str += ml.muteType.ToString()+" "+ml.muteID.ToString()+" "+ml.muteName+"|"+ml.muteFlags.ToString()+"\n";
				}
			}
			else {
				m_log.ErrorFormat("[NSL MUTE LIST] Not response from mute.php");
				return;
			}

			string filename = "mutes" + client.AgentId.ToString();
			IXfer xfer = client.Scene.RequestModuleInterface<IXfer>();
			if (xfer != null)
			{
 				byte[] byteArray = System.Text.Encoding.GetEncoding("UTF-8").GetBytes(str);
				xfer.AddNewFile(filename, byteArray);
				client.SendMuteListUpdate(filename);
			}
		}
	}



	public class GridMuteList
	{
		public Guid agentID;
		public Guid muteID;
		public string muteName;
		public int  muteType;
		public int  muteFlags;
		public uint timestamp;


		public GridMuteList()
		{ 
		}


		public GridMuteList(UUID _uuid, UUID _mute, string _name, int _type, int _flags)
		{
			agentID	  = _uuid.Guid;
			muteID	  = _mute.Guid;
			muteName  = _name;
			muteType  = _type;
			muteFlags = _flags;
			timestamp = (uint)Util.UnixTimeSinceEpoch();
		}
	}


}

