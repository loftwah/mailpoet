import React from 'react';
import PropTypes from 'prop-types';

import MSSUserSuccess from './success_for_mss_users.jsx';
import PitchMss from './success_pitch_mss.jsx';

import FeaturesContext from '../../../features/context.jsx';

function SuccessContent(props) {
  if (!window.has_premium_key && props.isSupported('display-mss-pitch')) {
    return (
      <PitchMss
        MSSPitchIllustrationUrl={props.MSSPitchIllustrationUrl}
        onFinish={props.successClicked}
        isWoocommerceActive={props.isWoocommerceActive}
        subscribersCount={props.subscribersCount}
        mailpoetAccountUrl={props.mailpoetAccountUrl}
      />
    );
  }
  return (
    <MSSUserSuccess
      successClicked={props.successClicked}
      illustrationImageUrl={props.illustrationImageUrl}
      newsletter={props.newsletter}
    />
  );
}

function Success(props) {
  return (
    <FeaturesContext.Consumer>
      {(FeaturesController) => (
        <SuccessContent
          {...props}
          isSupported={FeaturesController.isSupported}
        />
      )}
    </FeaturesContext.Consumer>
  );
}

Success.propTypes = {
  successClicked: PropTypes.func.isRequired,
  illustrationImageUrl: PropTypes.string.isRequired,
  MSSPitchIllustrationUrl: PropTypes.string.isRequired,
  newsletter: PropTypes.shape({
    status: PropTypes.string.isRequired,
    type: PropTypes.string.isRequired,
  }).isRequired,
  isWoocommerceActive: PropTypes.bool.isRequired,
  subscribersCount: PropTypes.number.isRequired,
  mailpoetAccountUrl: PropTypes.string.isRequired,
};

SuccessContent.propTypes = {
  successClicked: PropTypes.func.isRequired,
  illustrationImageUrl: PropTypes.string.isRequired,
  MSSPitchIllustrationUrl: PropTypes.string.isRequired,
  newsletter: PropTypes.shape({
    status: PropTypes.string.isRequired,
    type: PropTypes.string.isRequired,
  }).isRequired,
  isWoocommerceActive: PropTypes.bool.isRequired,
  subscribersCount: PropTypes.number.isRequired,
  mailpoetAccountUrl: PropTypes.string.isRequired,
  isSupported: PropTypes.func.isRequired,
};

export default Success;
