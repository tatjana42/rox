<?php

namespace App\Controller;

use App\Doctrine\GroupMembershipStatusType;
use App\Entity\Group;
use App\Entity\GroupMembership;
use App\Entity\Language;
use App\Entity\Member;
use App\Entity\MembersTrad;
use App\Entity\Preference;
use App\Form\CustomDataClass\GroupRequest;
use App\Form\GroupType;
use App\Logger\Logger;
use App\Repository\GroupRepository;
use Doctrine\DBAL\Statement;
use Html2Text\Html2Text;
use Intervention\Image\ImageManagerStatic as Image;
use Swift_Message;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class GroupController.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class GroupController extends AbstractController
{
    /**
     * @Route("/groups/new", name="new_group")
     *
     * @param Request             $request
     * @param TranslatorInterface $translator
     * @param Logger              $logger
     * @param \Swift_Mailer       $mailer
     *
     * @throws \Exception
     *
     * @return Response
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * Because of the mix between old code and new code this method is way too long.
     */
    public function createNewGroupAction(Request $request, TranslatorInterface $translator, Logger $logger, \Swift_Mailer $mailer)
    {
        $groupRequest = new GroupRequest();
        $form = $this->createForm(GroupType::class, $groupRequest);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $member = $this->getUser();
            $em = $this->getDoctrine()->getManager();

            $groupPicture = $this->handleGroupPicture($data->picture);

            // \todo: This is convoluted due to having to support the old structure! When recoding groups this should be simpler
            // We need the current locale for the MembersTrad entity
            $languageRepository = $em->getRepository(Language::class);
            /** @var Language $language */
            $language = $languageRepository->findOneBy(['shortcode' => $request->getLocale()]);
            /** @var Language $english */
            $english = $languageRepository->findOneBy(['shortcode' => 'en']);

            // We create the group entity and add the first member
            $group = new Group();
            $group
                ->setName($data->name)
                ->setType($data->type)
                ->setVisibleposts($data->membersOnly)
                ->setVisiblecomments(false)
                ->setMoreInfo('')
                ->setPicture($groupPicture)
            ;
            $em->persist($group);
            $em->flush();

            // Create the description as a member trad using the current language
            $description = new MembersTrad();
            $description
                ->setOwner($member)
                ->setIdTranslator($member->getId())
                ->setSentence($data->description)
                ->setIdrecord($group->getId())
                ->setLanguage($language);
            $em->persist($description);
            $em->flush();

            // Add a comment for the creator of the group in English
            $groupComment = new MembersTrad();
            $groupComment
                ->setOwner($member)
                ->setIdtranslator($member->getId())
                ->setSentence('Group creator')
                ->setIdrecord($group->getId())
                ->setLanguage($english);
            $em->persist($groupComment);
            $em->flush();

            $groupMembership = new GroupMembership();
            $groupMembership
                ->setStatus(GroupMembershipStatusType::CURRENT_MEMBER)
                ->addComment($groupComment)
                ->setGroup($group)
                ->setMember($member);

            $member->addGroupMembership($groupMembership);
            $group->addGroupMembership($groupMembership);

            // Link group and description
            $group->addDescription($description);
            $em->persist($group);
            $em->flush();

            // Now add the current member as admin for this group
            $connection = $this->getDoctrine()->getConnection();
            /** @var Statement $stmt */
            $stmt = $connection->prepare('
                REPLACE INTO 
                    `privilegescopes`
                SET
                    `Idmember` = :memberId,
                    `IdRole` = 2,
                    `IdPrivilege` = 3,
                    `IdType` = :groupId,
                    `updated` = :updated
            ');
            $stmt->execute([
                ':groupId' => $group->getId(),
                ':memberId' => $member->getId(),
                'updated' => (new \DateTime())->format('Y-m-d'),
            ]);

            $flashMessage = $translator->trans('group.create.successful', [
                '%name%' => $group->getName(),
            ]);
            $this->addFlash('notice', $flashMessage);

            $this->sendNewGroupNotifications($group, $member, $mailer);

            $logger->write('Group '.$group->getName().' created by '.$member->getUsername().'.', 'Group');

            return $this->redirectToRoute('groups_overview');
        }

        return $this->render('group/create.group.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/groups/new/check", name="new_group_check")
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function ajaxCheckNewGroupAction(Request $request)
    {
        $groupName = trim($request->request->get('name'));

        $html = '';
        if (!empty($groupName)) {
            $parts = explode(' ', $groupName);

            /** @var GroupRepository $groupRepository */
            $groupRepository = $this->getDoctrine()->getRepository(Group::class);
            $groups = $groupRepository->findByNameParts($parts);

            // Check if there are duplicate groups and provide a list of these

            $html = $this->renderView('group/check.group.html.twig', [
                'groups' => $groups,
            ]);
        }

        return new JsonResponse([
            'html' => $html,
        ]);
    }

    /**
     * Allows to set a status for group creation requests.
     *
     * @Route("/admin/groups/approval", name="admin_groups_approval")
     *
     * @throws AccessDeniedException
     *
     * @return Response
     */
    public function approveGroupsAction()
    {
        if (!$this->isGranted([Member::ROLE_ADMIN_GROUP])) {
            throw $this->createAccessDeniedException('You need to have Group right to access this.');
        }

        // Fetch unapproved groups and decide on their fate
        $groupsRepository = $this->getDoctrine()->getRepository(Group::class);
        $groups = $groupsRepository->findBy([
            'approved' => [Group::NOT_APPROVED, Group::IN_DISCUSSION],
        ]);

        return $this->render('admin/groups/approve.html.twig', [
            'groups' => $groups,
        ]);
    }

    /**
     * Move a group creation requests to the discussion queue.
     *
     * @Route("/admin/groups/{id}/discuss", name="admin_groups_discuss")
     *
     * @param Request             $request
     * @param Group               $group
     * @param TranslatorInterface $translator
     * @param Logger              $logger
     *
     * @throws \Exception
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function discussGroupAction(Request $request, Group $group, TranslatorInterface $translator, Logger $logger)
    {
        if (!$this->isGranted([Member::ROLE_ADMIN_GROUP])) {
            throw $this->createAccessDeniedException('You need to have Group right to access this.');
        }

        $group->setApproved(Group::IN_DISCUSSION);
        $em = $this->getDoctrine()->getManager();
        $em->persist($group);
        $em->flush();

        $flashMessage = $translator->trans('Moved group %name% into the discussion queue', [
            '%name%' => $group->getName(),
        ]);
        $this->addFlash('notice', $flashMessage);

        $logger->write('Group '.$group->getName().' moved into discussion by '.$this->getUser()->getUsername().'.', 'Group');

        $referrer = $request->headers->get('referer');

        return $this->redirect($referrer);
    }

    /**
     * Dismiss a group creation requests.
     *
     * @Route("/admin/groups/{id}/dismiss", name="admin_groups_dismiss")
     *
     * @param Request             $request
     * @param Group               $group
     * @param TranslatorInterface $translator
     * @param Logger              $logger
     *
     * @throws \Exception
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function dismissGroupAction(Request $request, Group $group, TranslatorInterface $translator, Logger $logger)
    {
        if (!$this->isGranted([Member::ROLE_ADMIN_GROUP])) {
            throw $this->createAccessDeniedException('You need to have Group right to access this.');
        }

        $group->setApproved(Group::DISMISSED);
        $em = $this->getDoctrine()->getManager();
        $em->persist($group);
        $em->flush();

        $flashMessage = $translator->trans('Dismissed group %name%', [
            '%name%' => $group->getName(),
        ]);
        $this->addFlash('notice', $flashMessage);

        $logger->write('Group '.$group->getName().' dismissed by '.$this->getUser()->getUsername().'.', 'Group');

        $referrer = $request->headers->get('referer');

        return $this->redirect($referrer);
    }

    /**
     * Approve a group creation requests.
     *
     * @Route("/admin/groups/{id}/approve", name="admin_groups_approve")
     *
     * @param Request             $request
     * @param Group               $group
     * @param TranslatorInterface $translator
     * @param Logger              $logger
     * @param \Swift_Mailer       $mailer
     *
     * @throws \Exception
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function approveGroupAction(
        Request $request,
        Group $group,
        TranslatorInterface $translator,
        Logger $logger,
        \Swift_Mailer $mailer
    ) {
        if (!$this->isGranted([Member::ROLE_ADMIN_GROUP])) {
            throw $this->createAccessDeniedException('You need to have the Group right to access this.');
        }

        $group->setApproved(Group::APPROVED);
        $em = $this->getDoctrine()->getManager();
        $em->persist($group);
        $em->flush();

        $flashMessage = $translator->trans('Approved creation for group %name% ', [
            '%name%' => $group->getName(),
        ]);
        $this->addFlash('notice', $flashMessage);

        $logger->write('Group '.$group->getName().' approved by '.$this->getUser()->getUsername().'.', 'Group');

        $creator = current($group->getMembers());
        $this->sendNewGroupApprovedNotification($group, $creator, $mailer);
        $referrer = $request->headers->get('referer');

        return $this->redirect($referrer);
    }

    private function sendNewGroupNotifications(Group $group, Member $member, \Swift_Mailer $mailer)
    {
        $recipients = $this->getNewGroupNotificationRecipients();
        $subject = '[New Group] '.strip_tags($group->getName());
        $message = new Swift_Message();
        $message
            ->setSubject($subject)
            ->setFrom(
                [
                    'groups@bewelcome.org' => 'BeWelcome - Group Administration',
                ]
            )
            ->setTo($recipients)
            ->setBody(
                $this->renderView('emails/new.group.html.twig', [
                    'subject' => $subject,
                    'group' => $group,
                    'member' => $member,
                ]),
                'text/html'
            )
        ;
        $recipients = $mailer->send($message);

        return (0 === $recipients) ? false : true;
    }

    private function sendNewGroupApprovedNotification(Group $group, Member $creator, \Swift_Mailer $mailer)
    {
        $recipient = $creator->getEmail();

        $preferenceRepository = $this->getDoctrine()->getRepository(Preference::class);
        /** @var Preference $preference */
        $preference = $preferenceRepository->findOneBy(['codename' => Preference::HTML_MAILS]);
        $htmlMails = ('Yes' === $creator->getMemberPreferenceValue($preference));

        $subject = '[New Group] '.strip_tags($group->getName()).' approved';
        $htmlBody = $this->renderView('emails/group.approved.html.twig', [
            'subject' => $subject,
            'group' => $group,
            'creator' => $creator,
        ]);

        $converter = new Html2Text($htmlBody, [
            'do_links' => 'table',
            'width' => 75
        ]);
        $body = $converter->getText();

        $message = new Swift_Message();
        $message
            ->setSubject($subject)
            ->setFrom(
                [
                    'groups@bewelcome.org' => 'BeWelcome - Group Administration',
                ]
            )
            ->setTo($recipient)
            ->setBody(
                $body,
                'text/plain'
            );
        if ($htmlMails) {
            $message->addPart(
                $htmlBody,
                'text/html'
            );
        }

        $recipients = $mailer->send($message);

        return (0 === $recipients) ? false : true;
    }

    private function getNewGroupNotificationRecipients()
    {
        $connection = $this->getDoctrine()->getConnection();
        $stmt = $connection->prepare("
            SELECT 
                m.Email 
            FROM 
                members m, 
                rightsvolunteers rv, 
                rights r 
            WHERE 
                r.Name = 'Group' 
                AND r.id = rv.IdRight 
                AND rv.Level = 10 
                AND rv.IdMember = m.id
                AND m.Status IN (".Member::ACTIVE_ALL.')
        ');
        $stmt->execute();
        $emails = $stmt->fetchAll();
        $recipients = [];
        foreach ($emails as $email) {
            if (!empty($email['Email'])) {
                $recipients[] = $email['Email'];
            }
        }

        return $recipients;
    }

    /**
     * @param UploadedFile $picture
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    private function handleGroupPicture($picture)
    {
        // if a file was uploaded move it into the image storage
        $groupImageDir = $this->getParameter('group_directory');
        $fileName = $this->generateUniqueFileName().'.'.$picture->guessExtension();

        // moves the file to the directory where group images are stored
        $picture->move(
            $groupImageDir,
            $fileName
        );
        $img = Image::make($groupImageDir.'/'.$fileName);
        $img->resize(80, 80, function ($constraint) {
            $constraint->aspectRatio();
        });
        $img->save($groupImageDir.'/thumb'.$fileName);

        return $fileName;
    }

    /**
     * @return string
     */
    private function generateUniqueFileName()
    {
        // md5() reduces the similarity of the file names generated by
        // uniqid(), which is based on timestamps
        return md5(uniqid());
    }
}
