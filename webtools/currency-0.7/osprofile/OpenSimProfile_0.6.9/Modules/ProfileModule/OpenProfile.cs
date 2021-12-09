using System;
using System.Collections;
using System.Collections.Generic;
using System.Globalization;
using System.Net;
using System.Net.Sockets;
using System.Reflection;
using System.Xml;
using OpenMetaverse;
using log4net;
using Nini.Config;
using Nwc.XmlRpc;
using OpenSim.Framework;
using OpenSim.Region.Framework.Interfaces;
using OpenSim.Region.Framework.Scenes;
using OpenSim.Services.Interfaces;


namespace OpenSimProfile.Modules.OpenProfile
{
	public class OpenProfileModule : IRegionModule
	{
		// added by Fumi.Iseki
		private string encode = "UTF-8";
		private	System.Text.Encoding Enc = null;

	
		//
		// Log module
		//
		private static readonly ILog m_log = LogManager.GetLogger(MethodBase.GetCurrentMethod().DeclaringType);

		//
		// Module vars
		//
		private IConfigSource m_gConfig;
		private List<Scene> m_Scenes = new List<Scene>();
		private string m_ProfileServer = "";
		private bool m_Enabled = true;


		public void Initialise(Scene scene, IConfigSource config)
		{
			if (!m_Enabled) return;

			IConfig profileConfig = config.Configs["Profile"];

			if (m_Scenes.Count == 0) // First time
			{
				if (profileConfig == null)
				{
					m_Enabled = false;
					return;
				}
				m_ProfileServer = profileConfig.GetString("ProfileURL", "");
				if (m_ProfileServer == "")
				{
					m_Enabled = false;
					return;
				}
				else
				{
					m_log.Info("[PROFILE] OpenProfile module is activated");
					m_Enabled = true;
				}
			}

			if (!m_Scenes.Contains(scene))
				m_Scenes.Add(scene);

			m_gConfig = config;

			// Hook up events
			scene.EventManager.OnNewClient += OnNewClient;

			// added by Fumi.Iseki
			Enc = System.Text.Encoding.GetEncoding(encode);
		}


		public void PostInitialise()
		{
			if (!m_Enabled)
				return;
		}


		public void Close()
		{
		}


		public string Name
		{
			get { return "ProfileModule"; }
		}


		public bool IsSharedModule
		{
			get { return true; }
		}


		ScenePresence FindPresence(UUID clientID)
        {
            ScenePresence p;

            foreach (Scene s in m_Scenes)
            {
                p = s.GetScenePresence(clientID);
                if (!p.IsChildAgent)
                    return p;
            }
            return null;
        }


		/// New Client Event Handler
		private void OnNewClient(IClientAPI client)
		{
			// Subscribe to messages

			// Classifieds
			client.AddGenericPacketHandler("avatarclassifiedsrequest", HandleAvatarClassifiedsRequest);
			client.OnClassifiedInfoRequest += ClassifiedInfoRequest;
			client.OnClassifiedInfoUpdate += ClassifiedInfoUpdate;
			client.OnClassifiedDelete += ClassifiedDelete;

			// Picks
			client.AddGenericPacketHandler("avatarpicksrequest", HandleAvatarPicksRequest);
			client.AddGenericPacketHandler("pickinforequest", HandlePickInfoRequest);
			client.OnPickInfoUpdate += PickInfoUpdate;
			client.OnPickDelete += PickDelete;

			// Notes
			client.AddGenericPacketHandler("avatarnotesrequest", HandleAvatarNotesRequest);
			client.OnAvatarNotesUpdate += AvatarNotesUpdate;

            // Profile
            client.OnRequestAvatarProperties += RequestAvatarProperties;
            client.OnUpdateAvatarProperties += UpdateAvatarProperties;
            client.OnAvatarInterestUpdate += AvatarInterestsUpdate;

			// Info (Preference)
            client.OnUserInfoRequest += UserPreferencesRequest;
            client.OnUpdateUserInfo += UpdateUserPreferences;
		}



		//
		// Make external XMLRPC request
		//
		private Hashtable GenericXMLRPCRequest(Hashtable ReqParams, string method)
		{
			ArrayList SendParams = new ArrayList();
			SendParams.Add(ReqParams);

			// Send Request
			XmlRpcResponse Resp;
			try
			{
				XmlRpcRequest Req = new XmlRpcRequest(method, SendParams);
				Resp = Req.Send(m_ProfileServer, 30000);
			}

			catch (WebException ex)
			{
				m_log.ErrorFormat("[PROFILE]: Unable to connect to Profile " +
						"Server {0}.  Exception {1}", m_ProfileServer, ex);
				Hashtable ErrorHash = new Hashtable();
				ErrorHash["success"] = false;
				ErrorHash["errorMessage"] = "Unable to search at this time. ";
				ErrorHash["errorURI"] = "";

				return ErrorHash;
			}

			catch (SocketException ex)
			{
				m_log.ErrorFormat(
						"[PROFILE]: Unable to connect to Profile Server {0}. Method {1}, params {2}. " +
						"Exception {3}", m_ProfileServer, method, ReqParams, ex);
				Hashtable ErrorHash = new Hashtable();
				ErrorHash["success"] = false;
				ErrorHash["errorMessage"] = "Unable to search at this time. ";
				ErrorHash["errorURI"] = "";

				return ErrorHash;
			}

			catch (XmlException ex)
			{
				m_log.ErrorFormat(
						"[PROFILE]: Unable to connect to Profile Server {0}. Method {1}, params {2}. " +
						"Exception {3}", m_ProfileServer, method, ReqParams.ToString(), ex);
				Hashtable ErrorHash = new Hashtable();
				ErrorHash["success"] = false;
				ErrorHash["errorMessage"] = "Unable to search at this time. ";
				ErrorHash["errorURI"] = "";

				return ErrorHash;
			}

			if (Resp.IsFault)
			{
				Hashtable ErrorHash = new Hashtable();
				ErrorHash["success"] = false;
				ErrorHash["errorMessage"] = "Unable to search at this time. ";
				ErrorHash["errorURI"] = "";
				return ErrorHash;
			}
			Hashtable RespData = (Hashtable)Resp.Value;

			return RespData;
		}



		///////////////////////////////////////////////////////////////////////////////////////////////
		//
		// Classified
		//

		// Request Classifieds Name
		public void HandleAvatarClassifiedsRequest(Object sender, string method, List<String> args) 
		{
            if (!(sender is IClientAPI)) return;

            IClientAPI remoteClient = (IClientAPI)sender;
			Hashtable ReqHash = new Hashtable();
			ReqHash["uuid"] = args[0];
			
			Hashtable result = GenericXMLRPCRequest(ReqHash, method);

			if (!Convert.ToBoolean(result["success"]))
			{
				remoteClient.SendAgentAlertMessage(result["errorMessage"].ToString(), false);
				return;
			}

            Dictionary<UUID, string> classifieds = new Dictionary<UUID, string>();
			ArrayList dataArray = (ArrayList)result["data"];

            if (dataArray != null && dataArray[0] != null)
			{
				string name;
				foreach (Object o in dataArray)
				{
					Hashtable d = (Hashtable)o;
				
					name = d["name"].ToString();
					if (Enc!=null) name = Enc.GetString(Convert.FromBase64String(name));
               		classifieds[new UUID(d["classifiedid"].ToString())] = name;
				}
			}

			remoteClient.SendAvatarClassifiedReply(remoteClient.AgentId, classifieds);
		}


		// Request Classifieds
		public void ClassifiedInfoRequest(UUID classifiedID, IClientAPI client)
		{
			Hashtable ReqHash = new Hashtable();

			ReqHash["avatar_id"] 	 = client.AgentId.ToString();
			ReqHash["classified_id"] = classifiedID.ToString();
			
			Hashtable result = GenericXMLRPCRequest(ReqHash, "classifiedinforequest");
			if (!Convert.ToBoolean(result["success"]))
			{
				client.SendAgentAlertMessage(result["errorMessage"].ToString(), false);
				return;
			}

			ArrayList dataArray = (ArrayList)result["data"];

            if (dataArray!=null && dataArray[0]!=null)
			{
				Hashtable d = (Hashtable)dataArray[0];

            	Vector3 globalPos = new Vector3();
				Vector3.TryParse(d["posglobal"].ToString(), out globalPos);

            	if (d["description"]==null) d["description"] = String.Empty;

				string name = d["name"].ToString();
				string desc = d["description"].ToString();
				if (Enc!=null) {
					name = Enc.GetString(Convert.FromBase64String(name));
					desc = Enc.GetString(Convert.FromBase64String(desc));
				}

				client.SendClassifiedInfoReply(	new UUID(d["classifieduuid"].ToString()),
                						 		new UUID(d["creatoruuid"].ToString()),
												Convert.ToUInt32(d["creationdate"]),
												Convert.ToUInt32(d["expirationdate"]),
												Convert.ToUInt32(d["category"]),
												name,
												desc,
												new UUID(d["parceluuid"].ToString()),
												Convert.ToUInt32(d["parentestate"]),
												new UUID(d["snapshotuuid"].ToString()),
					 							d["simname"].ToString(),
												globalPos,
												d["parcelname"].ToString(),
												Convert.ToByte(d["classifiedflags"]),
					 							Convert.ToInt32(d["priceforlisting"]));
			}
		}


		// Upfate Classifieds
		public void ClassifiedInfoUpdate(UUID queryclassifiedID, uint queryCategory, string queryName, string queryDescription, UUID queryParcelID, 
										uint queryParentEstate, UUID querySnapshotID, Vector3 queryGlobalPos, byte queryclassifiedFlags, 
										int queryclassifiedPrice, IClientAPI remoteClient)
		{
			Hashtable ReqHash = new Hashtable();

            ReqHash["creatorUUID"] 		= remoteClient.AgentId.ToString();
            ReqHash["classifiedUUID"] 	= queryclassifiedID.ToString();
			ReqHash["category"] 		= queryCategory.ToString();
			ReqHash["name"] 			= queryName;
			ReqHash["description"] 		= queryDescription;
            ReqHash["parentestate"] 	= queryParentEstate.ToString();
			ReqHash["snapshotUUID"] 	= querySnapshotID.ToString();
            ReqHash["sim_name"] 		= remoteClient.Scene.RegionInfo.RegionName;
            ReqHash["globalpos"] 		= queryGlobalPos.ToString();
            ReqHash["classifiedFlags"] 	= queryclassifiedFlags.ToString();
			ReqHash["classifiedPrice"] 	= queryclassifiedPrice.ToString();

            ScenePresence p = FindPresence(remoteClient.AgentId);

            Vector3 avaPos = p.AbsolutePosition;

            // Getting the parceluuid for this parcel

            ReqHash["parcel_uuid"] = p.currentParcelUUID.ToString();

            // Getting the global position for the Avatar

            Vector3 posGlobal = new Vector3(remoteClient.Scene.RegionInfo.RegionLocX * Constants.RegionSize + avaPos.X, 
											remoteClient.Scene.RegionInfo.RegionLocY * Constants.RegionSize + avaPos.Y, avaPos.Z);

            ReqHash["pos_global"] = posGlobal.ToString();

            Hashtable result = GenericXMLRPCRequest(ReqHash, "classified_update");

			if (!Convert.ToBoolean(result["success"]))
			{
				remoteClient.SendAgentAlertMessage(result["errorMessage"].ToString(), false);
				return;
			}
		}


		// Delete Classifieds
		public void ClassifiedDelete (UUID queryClassifiedID, IClientAPI remoteClient)
		{
			Hashtable ReqHash = new Hashtable();

			ReqHash["classifiedID"] = queryClassifiedID.ToString();

			Hashtable result = GenericXMLRPCRequest(ReqHash, "classified_delete");

			if (!Convert.ToBoolean(result["success"]))
			{
				remoteClient.SendAgentAlertMessage(result["errorMessage"].ToString(), false);
				return;
			}
		}



		///////////////////////////////////////////////////////////////////////////////////////////////
		//
		// Picks
		//

		// Request Picks Name
		public void HandleAvatarPicksRequest(Object sender, string method, List<String> args) 
		{
            if (!(sender is IClientAPI)) return;

            IClientAPI remoteClient = (IClientAPI)sender;

			Hashtable ReqHash = new Hashtable();
			ReqHash["uuid"] = args[0];
			
			Hashtable result = GenericXMLRPCRequest(ReqHash, method);

			if (!Convert.ToBoolean(result["success"]))
			{
				remoteClient.SendAgentAlertMessage(result["errorMessage"].ToString(), false);
				return;
			}

			ArrayList dataArray = (ArrayList)result["data"];

            Dictionary<UUID, string> picks = new Dictionary<UUID, string>();

            if (dataArray != null)
            {
				string name;
                foreach (Object o in dataArray)
                {
                    Hashtable d = (Hashtable)o;

					name = d["name"].ToString();
					if (Enc!=null) name = Enc.GetString(Convert.FromBase64String(name));
                    picks[new UUID(d["pickid"].ToString())] = name;
                }
            }

			remoteClient.SendAvatarPicksReply(remoteClient.AgentId, picks);		
		}


		// Request Picks
		public void HandlePickInfoRequest(Object sender, string method, List<String> args) 
		{
            if (!(sender is IClientAPI)) return;

            IClientAPI remoteClient = (IClientAPI)sender;

			Hashtable ReqHash = new Hashtable();

			ReqHash["avatar_id"] = args[0];
			ReqHash["pick_id"]   = args[1];
			
			Hashtable result = GenericXMLRPCRequest(ReqHash, method);

			if (!Convert.ToBoolean(result["success"]))
			{
				remoteClient.SendAgentAlertMessage(result["errorMessage"].ToString(), false);
				return;
			}

			ArrayList dataArray = (ArrayList)result["data"];

            if (dataArray!=null && dataArray[0]!=null)
			{
				Hashtable d = (Hashtable)dataArray[0];

            	Vector3 globalPos = new Vector3();
				Vector3.TryParse(d["posglobal"].ToString(), out globalPos);

            	if (d["description"]==null) d["description"] = String.Empty;

				string name = d["name"].ToString();
				string desc = d["description"].ToString();
				if (Enc!=null) {
					name = Enc.GetString(Convert.FromBase64String(name));
					desc = Enc.GetString(Convert.FromBase64String(desc));
				}

				remoteClient.SendPickInfoReply(	new UUID(d["pickuuid"].ToString()),
                    							new UUID(d["creatoruuid"].ToString()),
												Convert.ToBoolean(d["toppick"]),
												new UUID(d["parceluuid"].ToString()),
												name,
												desc,
												new UUID(d["snapshotuuid"].ToString()),
												d["user"].ToString(),
												d["originalname"].ToString(),
												d["simname"].ToString(),
												globalPos,
												Convert.ToInt32(d["sortorder"]),
												Convert.ToBoolean(d["enabled"]));
			}
		}


		// Update Picks
		public void PickInfoUpdate(IClientAPI remoteClient, UUID pickID, UUID creatorID, bool topPick, string name, string desc, UUID snapshotID, int sortOrder, bool enabled)
		{
			Hashtable ReqHash = new Hashtable();
			
            ReqHash["agent_id"] 	= remoteClient.AgentId.ToString();
            ReqHash["pick_id"] 		= pickID.ToString();
            ReqHash["creator_id"] 	= creatorID.ToString();
            ReqHash["top_pick"] 	= topPick.ToString();
            ReqHash["name"] 		= name;
            ReqHash["desc"] 		= desc;
            ReqHash["snapshot_id"] 	= snapshotID.ToString();
            ReqHash["sort_order"] 	= sortOrder.ToString();
            ReqHash["enabled"] 		= enabled.ToString();
            ReqHash["sim_name"]	 	= remoteClient.Scene.RegionInfo.RegionName;

            ScenePresence p = FindPresence(remoteClient.AgentId);

            Vector3 avaPos = p.AbsolutePosition;

            // Getting the parceluuid for this parcel
            ReqHash["parcel_uuid"] = p.currentParcelUUID.ToString();

            // Getting the global position for the Avatar
            Vector3 posGlobal = new Vector3(remoteClient.Scene.RegionInfo.RegionLocX*Constants.RegionSize + avaPos.X, 
											remoteClient.Scene.RegionInfo.RegionLocY*Constants.RegionSize + avaPos.Y, avaPos.Z);
            ReqHash["pos_global"] = posGlobal.ToString();

            // Getting the owner of the parcel

            // Getting the description of the parcel

            // Do the request
			Hashtable result = GenericXMLRPCRequest(ReqHash, "picks_update");

			if (!Convert.ToBoolean(result["success"]))
			{
				remoteClient.SendAgentAlertMessage(result["errorMessage"].ToString(), false);
				return;
			}
		}


		// Delete Picks
		public void PickDelete(IClientAPI remoteClient, UUID queryPickID)
		{
			Hashtable ReqHash = new Hashtable();
			
			ReqHash["pick_id"] = queryPickID.ToString();

			Hashtable result = GenericXMLRPCRequest(ReqHash, "picks_delete");

			if (!Convert.ToBoolean(result["success"]))
			{
				remoteClient.SendAgentAlertMessage(result["errorMessage"].ToString(), false);
				return;
			}
		}



		///////////////////////////////////////////////////////////////////////////////////////////////
		//
		// Notes
		//

		// Request Note (method: avatarnotesrequest)
		public void HandleAvatarNotesRequest(Object sender, string method, List<String> args) 
		{
            if (!(sender is IClientAPI)) return;

            IClientAPI remoteClient = (IClientAPI)sender;

			Hashtable ReqHash = new Hashtable();

			ReqHash["avatar_id"] = remoteClient.AgentId.ToString();
			ReqHash["target_id"] = args[0];
			
			// XMLRPC to profile.php
			Hashtable result = GenericXMLRPCRequest(ReqHash, method);

			if (!Convert.ToBoolean(result["success"]))
			{
				remoteClient.SendAgentAlertMessage(result["errorMessage"].ToString(), false);
				return;
			}

			// Anser from profile.php
			ArrayList dataArray = (ArrayList)result["data"];

            if (dataArray != null && dataArray[0] != null)
            {
                Hashtable d = (Hashtable)dataArray[0];

				string notes = d["notes"].ToString();
				if (Enc!=null) notes = Enc.GetString(Convert.FromBase64String(notes));
                remoteClient.SendAvatarNotesReply(new UUID(d["target_id"].ToString()), notes);
            }
            else
            {
                remoteClient.SendAvatarNotesReply(new UUID(ReqHash["target_id"].ToString()), "");
            }
		}


		// Update Note (method: avatar_notes_update)
		public void AvatarNotesUpdate(IClientAPI remoteClient, UUID queryTargetID, string queryNotes)
		{
			Hashtable ReqHash = new Hashtable();
			
			ReqHash["avatar_id"] = remoteClient.AgentId.ToString();
			ReqHash["target_id"] = queryTargetID.ToString();
			ReqHash["notes"]     = queryNotes;

			// XMLRPC to profile.php
			Hashtable result = GenericXMLRPCRequest(ReqHash, "avatar_notes_update");

			if (!Convert.ToBoolean(result["success"]))
			{
				remoteClient.SendAgentAlertMessage(result["errorMessage"].ToString(), false);
				return;
			}
		}
        



		///////////////////////////////////////////////////////////////////////////////////////////////
		//
		// Profile
		//

		// Request profile and Interests
        public void RequestAvatarProperties(IClientAPI remoteClient, UUID avatarID)
        {
            IScene s = remoteClient.Scene;
            if (!(s is Scene)) return;

            Scene scene = (Scene)s;

            //UserAccount account = scene.UserAccountService.GetUserAccount(scene.RegionInfo.ScopeID, avatarID);
			UserProfileData account = scene.CommsManager.UserService.GetUserProfile(avatarID);
            if (null != account)
            {
                Byte[] charterMember;
                charterMember = new Byte[1];
                charterMember[0] = (Byte)((account.UserFlags & 0xf00) >> 8);

                //Hashtable profileData = GetProfileData(remoteClient.AgentId);
                Hashtable profileData = GetProfileData(avatarID);

                string profileUrl = String.Empty;
                string aboutText = String.Empty;
                string firstLifeText = String.Empty;
				string timeformat = "M/d/yyyy";
                UUID firstLifeImage = UUID.Zero;
                UUID image = UUID.Zero;
                UUID partner = UUID.Zero;

                if (profileData["Partner"] != null)   	   partner = new UUID(profileData["Partner"].ToString());
                if (profileData["ProfileUrl"] != null) 	   profileUrl = profileData["ProfileUrl"].ToString();
                if (profileData["Image"] != null) 		   image = new UUID(profileData["Image"].ToString());
                if (profileData["AboutText"] != null) 	   aboutText = profileData["AboutText"].ToString();
                if (profileData["FirstLifeImage"] != null) firstLifeImage = new UUID(profileData["FirstLifeImage"].ToString());
                if (profileData["FirstLifeText"] != null)  firstLifeText = profileData["FirstLifeText"].ToString();

				if (Enc!=null) {
					aboutText     = Enc.GetString(Convert.FromBase64String(aboutText));
					firstLifeText = Enc.GetString(Convert.FromBase64String(firstLifeText));
					//profileUrl    = Enc.GetString(Convert.FromBase64String(profileUrl));
				}

                remoteClient.SendAvatarProperties(avatarID, aboutText,
                          Util.ToDateTime(account.Created).ToString(timeformat, CultureInfo.InvariantCulture),
                          charterMember, firstLifeText,
                          (uint)(account.UserFlags & 0xff),
                          firstLifeImage, image, profileUrl, partner);

				////////////////////////////////////////////////////////////////////////////////////////////////////////
				// Request Interests
				string wantoText = String.Empty;
				string skillText = String.Empty;
				string langText  = String.Empty;
				uint   wantoMask = 0;
				uint   skillMask = 0;

                if (profileData["WantToMask"] != null)    wantoMask = Convert.ToUInt32(profileData["WantToMask"]);
                if (profileData["WantToText"] != null)    wantoText = profileData["WantToText"].ToString();
                if (profileData["SkillsMask"] != null)    skillMask = Convert.ToUInt32(profileData["SkillsMask"]);
                if (profileData["SkillsText"] != null)    skillText = profileData["SkillsText"].ToString();
                if (profileData["LanguagesText"] != null) langText  = profileData["LanguagesText"].ToString();

				if (Enc!=null) {
					wantoText = Enc.GetString(Convert.FromBase64String(wantoText));
					skillText = Enc.GetString(Convert.FromBase64String(skillText));
					langText  = Enc.GetString(Convert.FromBase64String(langText));
				}

				remoteClient.SendAvatarInterestsReply(avatarID, wantoMask, wantoText, skillMask, skillText, langText);
            }
            else
            {
                m_log.Debug("[AvatarProfilesModule]: Got null for profile for " + avatarID.ToString());
            }
        }


		// Update Profile 
        public void UpdateAvatarProperties(IClientAPI remoteClient, UserProfileData newProfile)
        {
            if (remoteClient.AgentId == newProfile.ID)
            {
                string profileUrl 	  = newProfile.ProfileUrl;
                string image 		  = newProfile.Image.ToString();
                string firstLifeImage = newProfile.FirstLifeImage.ToString();
                string aboutText 	  = newProfile.AboutText;
                string firstLifeText  = newProfile.FirstLifeAboutText;

            	Hashtable ReqHash = new Hashtable();

				ReqHash["avatar_id"] 	  = remoteClient.AgentId.ToString();
            	ReqHash["ProfileUrl"] 	  = profileUrl;
            	ReqHash["Image"] 		  = image;
            	ReqHash["FirstLifeImage"] = firstLifeImage;
            	ReqHash["AboutText"] 	  = aboutText;
            	ReqHash["FirstLifeText"]  = firstLifeText;

				Hashtable result = GenericXMLRPCRequest(ReqHash, "avatar_properties_update");

				if (!Convert.ToBoolean(result["success"]))
				{
					remoteClient.SendAgentAlertMessage(result["errorMessage"].ToString(), false);
				}       
				return;
            }
        }


        // Called from RequestAvatarProperties()
        private Hashtable GetProfileData(UUID userID)
        {
            Hashtable ReqHash = new Hashtable();

            ReqHash["avatar_id"] = userID.ToString();

            Hashtable result = GenericXMLRPCRequest(ReqHash, "avatar_properties_request");
            
            ArrayList dataArray = (ArrayList)result["data"];

            if (dataArray != null && dataArray[0] != null)
            {
                Hashtable d = (Hashtable)dataArray[0];
                return d;
            }
            return result;
        }



		///////////////////////////////////////////////////////////////////////////////////////////////
		//
		// Interests 
		//

        // Update Interests
        public void AvatarInterestsUpdate(IClientAPI remoteClient, uint wantmask, string wanttext, uint skillsmask, string skillstext, string languages)
        {
            Hashtable ReqHash = new Hashtable();

            ReqHash["avatar_id"]  	 = remoteClient.AgentId.ToString();
            ReqHash["WantToMask"] 	 = wantmask.ToString();
            ReqHash["WantToText"] 	 = wanttext;
            ReqHash["SkillsMask"] 	 = skillsmask.ToString();
            ReqHash["SkillsText"] 	 = skillstext;
            ReqHash["LanguagesText"] = languages;

            Hashtable result = GenericXMLRPCRequest(ReqHash, "avatar_interests_update");

            if (!Convert.ToBoolean(result["success"]))
            {
                remoteClient.SendAgentAlertMessage(result["errorMessage"].ToString(), false);
                return;
            }
        }



		///////////////////////////////////////////////////////////////////////////////////////////////
		//
		// Preference ???
		//

        public void UserPreferencesRequest(IClientAPI remoteClient)
        {
            Hashtable ReqHash = new Hashtable();

            ReqHash["avatar_id"] = remoteClient.AgentId.ToString();
            
            Hashtable result = GenericXMLRPCRequest(ReqHash, "user_preferences_request");

            if (!Convert.ToBoolean(result["success"]))
            {
                remoteClient.SendAgentAlertMessage(result["errorMessage"].ToString(), false);
                return;
            }

            ArrayList dataArray = (ArrayList)result["data"];

            if (dataArray != null && dataArray[0] != null)
            {
                Hashtable d = (Hashtable)dataArray[0];

                remoteClient.SendUserInfoReply( Convert.ToBoolean(d["imviaemail"]), 
                        						Convert.ToBoolean(d["visible"]), 
                        						d["email"].ToString());
            }
        }



        public void UpdateUserPreferences(bool imViaEmail, bool visible, IClientAPI remoteClient)
        {
            Hashtable ReqHash = new Hashtable();

            ReqHash["avatar_id"]  = remoteClient.AgentId.ToString();
            ReqHash["imViaEmail"] = imViaEmail.ToString();
            ReqHash["visible"]    = visible.ToString();

            Hashtable result = GenericXMLRPCRequest(ReqHash, "user_preferences_update");

            if (!Convert.ToBoolean(result["success"]))
            {
                remoteClient.SendAgentAlertMessage(result["errorMessage"].ToString(), false);
                return;
            }
        }


	}
}
