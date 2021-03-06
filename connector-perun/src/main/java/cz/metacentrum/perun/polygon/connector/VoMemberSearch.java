package cz.metacentrum.perun.polygon.connector;

import java.util.ArrayList;
import java.util.List;
import java.util.stream.Collectors;

import org.identityconnectors.common.logging.Log;
import org.identityconnectors.framework.common.objects.AttributeUtil;
import org.identityconnectors.framework.common.objects.ConnectorObjectBuilder;
import org.identityconnectors.framework.common.objects.ObjectClass;
import org.identityconnectors.framework.common.objects.OperationOptions;
import org.identityconnectors.framework.common.objects.ResultsHandler;
import org.identityconnectors.framework.common.objects.SearchResult;
import org.identityconnectors.framework.common.objects.Uid;
import org.identityconnectors.framework.common.objects.filter.EqualsFilter;
import org.identityconnectors.framework.common.objects.filter.EqualsIgnoreCaseFilter;
import org.identityconnectors.framework.common.objects.filter.Filter;
import org.identityconnectors.framework.spi.SearchResultsHandler;
import org.springframework.web.client.HttpClientErrorException;

import cz.metacentrum.perun.polygon.connector.rpc.PerunRPC;
import cz.metacentrum.perun.polygon.connector.rpc.model.Member;
import cz.metacentrum.perun.polygon.connector.rpc.model.PerunBean;
import cz.metacentrum.perun.polygon.connector.rpc.model.RichMember;
import cz.metacentrum.perun.polygon.connector.rpc.model.Vo;

public class VoMemberSearch extends ObjectSearchBase implements ObjectSearch {

	private static final Log LOG = Log.getLog(VoMemberSearch.class);
	
	public VoMemberSearch(ObjectClass objectClass, SchemaAdapter adapter, PerunRPC perun) {
		super(objectClass, adapter, perun);
	}

	@Override
	public PerunBean readPerunBeanById(Integer id, Integer... ids) {
		LOG.info("Reading member with uid {0}", id);
		RichMember member = null;
		try {
			member = perun.getMembersManager().getRichMemberWithAttributes(id);
		} catch (HttpClientErrorException exception) {
			return null;
		}
		return member;
	}

	@Override
	public void executeQuery(Filter filter, OperationOptions options, ResultsHandler handler) {
		if(filter == null) {
			// read all
			readAllMembers(options, handler);
			return;
		}

		// perform search
		if(filter instanceof EqualsFilter) {
			if(((EqualsFilter)filter).getAttribute().is(Uid.NAME)) {
				// read single object
				String uid = (String)AttributeUtil.getSingleValue(((EqualsFilter)filter).getAttribute());
				RichMember member = (RichMember)readPerunBeanById(Integer.valueOf(uid));
				if(member != null) {
					mapResult(member, handler);
				}
				SearchResult result = new SearchResult(
						 null, 	/* cookie */ 
						 -1,	/* remainingResults */
						 true	/* completeResultSet */
						 );
				((SearchResultsHandler)handler).handleResult(result);
				return;
			}
			
		} else if(filter instanceof EqualsIgnoreCaseFilter) {
			
		} else {
			LOG.warn("Filter of type {0} is not supported", filter.getClass().getName());
			throw new RuntimeException("Unsupported query");
		}

	}

	protected void readAllMembers(OperationOptions options, ResultsHandler handler) {
		Integer pageSize = options.getPageSize();
		Integer pageOffset = options.getPagedResultsOffset();
		String pageResultsCookie = options.getPagedResultsCookie();
		

		List<RichMember> members = new ArrayList<RichMember>();
		int remaining = -1;
		
		LOG.info("Reading {0} members from page at offset {1}", pageSize, pageOffset);
		if(pageSize != null && pageSize > 0) {
			List<Member> partMembers = new ArrayList<Member>();
			LOG.info("Reading list of members.");
			partMembers.addAll(perun.getMembersManager().getAllMembers());
			LOG.info("Total members acquired: {0}", partMembers.size());
			List<Integer> memberIds = partMembers.stream()
				.map(member -> { return member.getId(); })
				.sorted()
				.collect(Collectors.toList());
			int size = memberIds.size();
			int last = (pageOffset + pageSize > size) ? size : pageOffset + pageSize; 
			memberIds = memberIds.subList(pageOffset, last);
			remaining = size - last;
			members.addAll(perun.getMembersManager().getRichMembersByIds(memberIds, null));
		} else {
			LOG.info("Reading list of VOs");
			List<Vo> vos = perun.getVosManager().getAllVos();
			LOG.info("Query returned {0} VOs", vos.size());

			for(Vo vo : vos) {
				LOG.info("Reading list of members for VO: {0}", vo.getId());
				members.addAll(perun.getMembersManager().getCompleteRichMembersForVo(vo.getId(), null, null));
				LOG.info("Total members read so far: {0}", members.size());
			}
		}
		LOG.info("Query returned {0} members", members.size());
		for(RichMember member : members) {
			mapResult(member, handler);
		}
		SearchResult result = new SearchResult(
				 pageResultsCookie, 	/* cookie */ 
				 remaining,	/* remainingResults */
				 true	/* completeResultSet */
				 );
		((SearchResultsHandler)handler).handleResult(result);
	}

	private void mapResult(RichMember member, ResultsHandler handler) {
		ConnectorObjectBuilder out = schemaAdapter.mapObject(objectClass, member);
		handler.handle(out.build());
	}

}
